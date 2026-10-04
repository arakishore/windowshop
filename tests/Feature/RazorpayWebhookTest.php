<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\Payment\PaymentAccountResolver;
use App\Services\Payment\PaymentAccountService;
use App\Services\Payment\RazorpayGateway;
use App\Services\Payment\RazorpayPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private FakeWebhookRazorpayGateway $gateway;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeWebhookRazorpayGateway;
        $this->app->instance(RazorpayGateway::class, $this->gateway);
    }

    public function test_valid_captured_webhook_fetches_provider_and_marks_payment_paid_without_confirming_order(): void
    {
        [$account, $attempt, $order] = $this->fixture();
        $this->gateway->payments['pay_ok'] = $this->providerPayment($attempt, 'pay_ok', ['acquirer_data' => ['rrn' => ' 123456789 ']]);

        $this->postWebhook($account, $this->payload('payment.captured', $attempt, 'pay_ok'), 'evt_capture')->assertOk();

        $this->assertSame(PaymentAttempt::PAID, $attempt->fresh()->status);
        $this->assertSame('pay_ok', $attempt->fresh()->provider_payment_id);
        $this->assertSame('123456789', data_get($attempt->fresh()->metadata, 'bank_rrn'));
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
        $this->assertSame('1998.00', $order->fresh()->amount_paid);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->order_status);
        $this->assertDatabaseHas('payment_webhook_events', ['provider_event_id' => 'evt_capture', 'disposition' => 'processed']);
    }

    public function test_invalid_missing_signature_unknown_token_and_malformed_payload_never_change_state(): void
    {
        [$account, $attempt] = $this->fixture();
        $raw = $this->payload('payment.captured', $attempt, 'pay_bad');
        $this->postJson(route('payments.razorpay.webhook', 'unknown-token'), [])->assertNotFound();
        $this->call('POST', route('payments.razorpay.webhook', $account->webhook_token), [], [], [], [], $raw)->assertBadRequest();
        $this->call('POST', route('payments.razorpay.webhook', $account->webhook_token), [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => 'bad'], $raw)->assertBadRequest();
        $badJson = '{bad';
        $this->call('POST', route('payments.razorpay.webhook', $account->webhook_token), [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => $this->signature($badJson)], $badJson)->assertBadRequest();
        $this->assertSame(PaymentAttempt::PENDING, $attempt->fresh()->status);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_missing_webhook_secret_is_retryable_and_does_not_process(): void
    {
        [$account, $attempt] = $this->fixture(false);
        $raw = $this->payload('payment.captured', $attempt, 'pay_no_secret');
        $this->call('POST', route('payments.razorpay.webhook', $account->webhook_token), [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => 'anything'], $raw)->assertStatus(503);
        $this->assertSame(PaymentAttempt::PENDING, $attempt->fresh()->status);
    }

    public function test_unsupported_event_is_audited_and_acknowledged_without_processing(): void
    {
        [$account, $attempt] = $this->fixture();
        $this->postWebhook($account, $this->payload('payment.authorized', $attempt, 'pay_auth'), 'evt_unsupported')->assertOk();
        $this->assertSame(PaymentAttempt::PENDING, $attempt->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', ['provider_event_id' => 'evt_unsupported', 'disposition' => 'ignored_unsupported']);
    }

    public function test_unknown_and_cross_account_orders_are_rejected_without_global_lookup(): void
    {
        [$accountA, $attemptA] = $this->fixture();
        [$accountB] = $this->fixture(true, 'live');
        $unknown = $this->payload('payment.captured', $attemptA, 'pay_unknown', 'order_unknown');
        $this->postWebhook($accountA, $unknown, 'evt_unknown')->assertServiceUnavailable();
        $this->postWebhook($accountB, $this->payload('payment.captured', $attemptA, 'pay_cross'), 'evt_cross')->assertServiceUnavailable();
        $this->assertSame(PaymentAttempt::PENDING, $attemptA->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', ['provider_event_id' => 'evt_cross', 'disposition' => 'retryable', 'failure_reason' => 'unknown_provider_order', 'processed_at' => null]);
    }

    public function test_provider_fetch_rejects_amount_currency_status_and_identifier_mismatches(): void
    {
        foreach (['amount', 'currency', 'status', 'order_id', 'id'] as $index => $field) {
            [$account, $attempt] = $this->fixture();
            $paymentId = 'pay_mismatch_'.$index;
            $payment = $this->providerPayment($attempt, $paymentId);
            $payment[$field] = match ($field) {
                'amount' => 1, 'currency' => 'USD', 'status' => 'authorized', 'order_id' => 'order_wrong', 'id' => 'pay_wrong'
            };
            $this->gateway->payments[$paymentId] = $payment;
            $this->postWebhook($account, $this->payload('payment.captured', $attempt, $paymentId), 'evt_mismatch_'.$index)->assertOk();
            $this->assertSame(PaymentAttempt::PENDING, $attempt->fresh()->status);
        }
    }

    public function test_duplicate_delivery_and_already_paid_attempt_are_safe_no_ops(): void
    {
        [$account, $attempt, $order] = $this->fixture();
        $raw = $this->payload('payment.captured', $attempt, 'pay_duplicate');
        $this->gateway->payments['pay_duplicate'] = $this->providerPayment($attempt, 'pay_duplicate');
        $this->postWebhook($account, $raw, 'evt_duplicate')->assertOk();
        $paidAt = $attempt->fresh()->paid_at;
        $this->postWebhook($account, $raw, 'evt_duplicate')->assertOk()->assertJson(['status' => 'duplicate']);
        $this->postWebhook($account, $raw, 'evt_second_delivery')->assertOk()->assertJson(['status' => 'already_paid']);
        $this->assertDatabaseCount('payment_webhook_events', 2);
        $this->assertSame($paidAt->toISOString(), $attempt->fresh()->paid_at->toISOString());
        $this->assertSame('1998.00', $order->fresh()->amount_paid);
    }

    public function test_callback_and_webhook_can_arrive_in_either_order_without_duplicate_transition(): void
    {
        [$accountA, $attemptA, $orderA] = $this->fixture();
        $this->gateway->payments['pay_callback_first'] = $this->providerPayment($attemptA, 'pay_callback_first');
        app(RazorpayPaymentService::class)->verify($attemptA, 'pay_callback_first', $attemptA->provider_order_id, 'checkout-signature');
        $this->postWebhook($accountA, $this->payload('payment.captured', $attemptA, 'pay_callback_first'), 'evt_after_callback')->assertOk()->assertJson(['status' => 'already_paid']);

        [$accountB, $attemptB, $orderB] = $this->fixture();
        $this->gateway->payments['pay_webhook_first'] = $this->providerPayment($attemptB, 'pay_webhook_first');
        $this->postWebhook($accountB, $this->payload('payment.captured', $attemptB, 'pay_webhook_first'), 'evt_before_callback')->assertOk();
        app(RazorpayPaymentService::class)->verify($attemptB, 'pay_webhook_first', $attemptB->provider_order_id, 'checkout-signature');

        $this->assertSame('1998.00', $orderA->fresh()->amount_paid);
        $this->assertSame('1998.00', $orderB->fresh()->amount_paid);
        $this->assertSame(PaymentAttempt::PAID, $attemptA->fresh()->status);
        $this->assertSame(PaymentAttempt::PAID, $attemptB->fresh()->status);
    }

    public function test_payment_failed_records_failure_but_never_downgrades_paid_or_cancels_order(): void
    {
        [$account, $attempt, $order] = $this->fixture();
        $this->postWebhook($account, $this->payload('payment.failed', $attempt, 'pay_failed'), 'evt_failed')->assertOk();
        $this->assertSame(PaymentAttempt::FAILED, $attempt->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->order_status);

        $attempt->forceFill(['status' => PaymentAttempt::PAID, 'paid_at' => now()])->save();
        $this->postWebhook($account, $this->payload('payment.failed', $attempt, 'pay_failed'), 'evt_failed_after_paid')->assertOk();
        $this->assertSame(PaymentAttempt::PAID, $attempt->fresh()->status);
    }

    public function test_disabled_account_can_reconcile_existing_attempt_but_is_not_available_for_new_checkout(): void
    {
        [$account, $attempt] = $this->fixture();
        $account->forceFill(['enabled' => false])->save();
        $this->gateway->payments['pay_disabled'] = $this->providerPayment($attempt, 'pay_disabled');
        $this->postWebhook($account, $this->payload('payment.captured', $attempt, 'pay_disabled'), 'evt_disabled')->assertOk();
        $this->assertSame(PaymentAttempt::PAID, $attempt->fresh()->status);
        $this->assertNull(app(PaymentAccountResolver::class)->resolveForShop($attempt->shop, 'razorpay', $account->mode));
    }

    public function test_captured_payment_for_cancelled_order_is_audited_for_review_without_revival(): void
    {
        [$account, $attempt, $order] = $this->fixture();
        $order->forceFill(['order_status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()])->save();
        $this->gateway->payments['pay_cancelled'] = $this->providerPayment($attempt, 'pay_cancelled');
        $this->postWebhook($account, $this->payload('payment.captured', $attempt, 'pay_cancelled'), 'evt_cancelled')->assertOk();
        $this->assertSame(PaymentAttempt::PENDING, $attempt->fresh()->status);
        $this->assertNotSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->order_status);
        $this->assertDatabaseHas('payment_webhook_events', ['provider_event_id' => 'evt_cancelled', 'disposition' => 'requires_review']);
    }

    public function test_receipt_stores_hash_and_allowlisted_fields_not_raw_sensitive_payload(): void
    {
        [$account, $attempt] = $this->fixture();
        $raw = $this->payload('payment.failed', $attempt, 'pay_sensitive', null, ['email' => 'secret@example.test', 'contact' => '9999999999']);
        $this->postWebhook($account, $raw, 'evt_safe')->assertOk();
        $receipt = PaymentWebhookEvent::query()->sole();
        $this->assertSame(hash('sha256', $raw), $receipt->payload_hash);
        $this->assertStringNotContainsString('secret@example.test', json_encode($receipt->getAttributes()));
        $this->assertArrayNotHasKey('payload', $receipt->getAttributes());
    }

    public function test_payment_account_tokens_are_opaque_unique_stable_and_hidden(): void
    {
        [$first] = $this->fixture();
        [$second] = $this->fixture(true, 'live');
        $token = $first->webhook_token;
        $first->update(['public_key' => 'rzp_test_changed']);
        app(PaymentAccountService::class)->updateSecrets($first, 'changed', 'changed-webhook');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
        $this->assertNotSame($token, $second->webhook_token);
        $this->assertSame($token, $first->fresh()->webhook_token);
        $this->assertArrayNotHasKey('webhook_token', $first->fresh()->toArray());
        $this->assertArrayNotHasKey('webhook_secret', $first->fresh()->toArray());
    }

    private function fixture(bool $withWebhookSecret = true, string $mode = 'test'): array
    {
        $user = User::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'Merchant', 'email' => Str::random(8).'@example.test', 'mobile' => '9'.random_int(100000000, 999999999), 'password' => Hash::make('password'), 'status' => 'active']);
        $merchant = MerchantProfile::query()->create(['user_id' => $user->getKey(), 'business_name' => 'Business '.Str::random(5), 'verification_status' => 'approved', 'status' => 'active']);
        $category = ProductCategory::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'Category '.Str::random(5), 'slug' => 'category-'.Str::random(8), 'status' => 'active']);
        $shop = Shop::query()->create(['merchant_id' => $merchant->getKey(), 'root_product_category_id' => $category->getKey(), 'name' => 'Shop', 'slug' => 'shop-'.Str::random(8), 'address_line_1' => 'Road', 'status' => 'active']);
        $account = app(PaymentAccountService::class)->create($merchant, ['provider' => 'razorpay', 'name' => ucfirst($mode), 'mode' => $mode, 'enabled' => true, 'public_key' => 'rzp_'.$mode.'_'.Str::random(6), 'secret' => 'api-secret', 'webhook_secret' => $withWebhookSecret ? 'webhook-secret' : null]);
        app(PaymentAccountService::class)->map($account, $shop);
        $order = Order::query()->create(['uuid' => (string) Str::uuid(), 'order_number' => 'WS-'.Str::upper(Str::random(8)), 'merchant_id' => $merchant->getKey(), 'shop_id' => $shop->getKey(), 'created_source' => Order::SOURCE_STOREFRONT, 'order_status' => Order::STATUS_PENDING, 'payment_method' => 'online_payment', 'payment_status' => Order::PAYMENT_PENDING, 'currency_code' => 'INR', 'grand_total' => '1998.00', 'amount_paid' => '0.00']);
        $attempt = PaymentAttempt::query()->create(['order_id' => $order->getKey(), 'shop_id' => $shop->getKey(), 'payment_account_id' => $account->getKey(), 'provider' => 'razorpay', 'provider_order_id' => 'order_'.Str::random(12), 'amount_minor' => 199800, 'currency' => 'INR', 'status' => PaymentAttempt::PENDING, 'initiated_at' => now()]);

        return [$account, $attempt, $order];
    }

    private function providerPayment(PaymentAttempt $attempt, string $paymentId, array $extra = []): array
    {
        return [...['id' => $paymentId, 'order_id' => $attempt->provider_order_id, 'amount' => $attempt->amount_minor, 'currency' => $attempt->currency, 'status' => 'captured'], ...$extra];
    }

    private function payload(string $event, PaymentAttempt $attempt, string $paymentId, ?string $orderId = null, array $extra = []): string
    {
        return json_encode(['event' => $event, 'payload' => ['payment' => ['entity' => [...['id' => $paymentId, 'order_id' => $orderId ?? $attempt->provider_order_id, 'amount' => $attempt->amount_minor, 'currency' => $attempt->currency, 'status' => str_ends_with($event, 'failed') ? 'failed' : 'captured'], ...$extra]]]], JSON_THROW_ON_ERROR);
    }

    private function postWebhook(PaymentAccount $account, string $raw, string $eventId)
    {
        return $this->call('POST', route('payments.razorpay.webhook', $account->webhook_token), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $this->signature($raw), 'HTTP_X_RAZORPAY_EVENT_ID' => $eventId], $raw);
    }

    private function signature(string $raw): string
    {
        return hash_hmac('sha256', $raw, 'webhook-secret');
    }
}

class FakeWebhookRazorpayGateway implements RazorpayGateway
{
    public array $payments = [];

    public function createOrder(string $keyId, string $secret, array $attributes): array
    {
        return [];
    }

    public function verifyPaymentSignature(string $keyId, string $secret, array $attributes): void {}

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): void
    {
        if (! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            throw new RuntimeException('Invalid signature');
        }
    }

    public function fetchPayment(string $keyId, string $secret, string $paymentId): array
    {
        return $this->payments[$paymentId] ?? throw new RuntimeException('Missing payment');
    }
}
