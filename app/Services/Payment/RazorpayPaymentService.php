<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RazorpayPaymentService
{
    public const PROVIDER = 'razorpay';

    public const MODE = 'test';

    public function __construct(
        private readonly PaymentAccountResolver $accounts,
        private readonly PaymentAccountService $accountSecrets,
        private readonly PaymentAttemptService $attempts,
        private readonly RazorpayGateway $gateway,
    ) {}

    public function availableForShop(Shop $shop): bool
    {
        $account = $this->accounts->resolveForShop($shop, self::PROVIDER, self::MODE);

        return $account !== null && filled($this->accountSecrets->secret($account));
    }

    public function initiate(Order $order): array
    {
        $order->loadMissing(['shop', 'customer']);
        if ($order->payment_method !== 'online_payment' || $order->payment_status === Order::PAYMENT_PAID || ! $order->shop) {
            throw ValidationException::withMessages(['payment' => 'This order is not available for online payment.']);
        }
        $account = $this->accounts->resolveForShop($order->shop, self::PROVIDER, self::MODE);
        $secret = $account ? $this->accountSecrets->secret($account) : null;
        if (! $account || blank($secret)) {
            throw ValidationException::withMessages(['payment' => 'Online Payment is currently unavailable.']);
        }

        $amountMinor = $this->minorUnits((string) $order->grand_total);
        $attempt = $this->attempts->create($order, $order->shop, $account, $amountMinor, strtoupper($order->currency_code), [
            'status' => PaymentAttempt::CREATED,
            'initiated_at' => now(),
        ]);
        try {
            $providerOrder = $this->gateway->createOrder((string) $account->public_key, $secret, [
                'amount' => $amountMinor,
                'currency' => strtoupper($order->currency_code),
                'receipt' => (string) $order->order_number,
                'notes' => ['windowshop_order' => (string) $order->order_number],
            ]);
            if (blank($providerOrder['id'] ?? null)
                || (int) ($providerOrder['amount'] ?? -1) !== $amountMinor
                || strtoupper((string) ($providerOrder['currency'] ?? '')) !== strtoupper($order->currency_code)) {
                throw new \RuntimeException('Invalid provider order response.');
            }
            $attempt->forceFill(['provider_order_id' => $providerOrder['id'], 'status' => PaymentAttempt::PENDING])->save();
        } catch (Throwable) {
            $attempt->forceFill(['status' => PaymentAttempt::FAILED, 'failed_at' => now(), 'failure_code' => 'provider_order_failed'])->save();
            throw ValidationException::withMessages(['payment' => 'Razorpay could not be started. Please try again.']);
        }

        return [
            'attempt_id' => $attempt->getKey(), 'key' => (string) $account->public_key,
            'order_id' => (string) $attempt->provider_order_id, 'amount' => $amountMinor,
            'currency' => strtoupper($order->currency_code), 'name' => (string) $account->name,
            'description' => 'Order '.$order->order_number,
            'prefill' => ['name' => $order->customer_name, 'email' => $order->customer_email, 'contact' => $order->customer_mobile],
        ];
    }

    public function verify(PaymentAttempt $attempt, string $paymentId, string $providerOrderId, string $signature): Order
    {
        $attempt->loadMissing('paymentAccount');
        if ($attempt->provider !== self::PROVIDER || $attempt->paymentAccount?->mode !== self::MODE
            || ! hash_equals((string) $attempt->provider_order_id, $providerOrderId)) {
            throw ValidationException::withMessages(['payment' => 'The payment response does not match this attempt.']);
        }
        $secret = $this->accountSecrets->secret($attempt->paymentAccount);
        if (blank($secret)) {
            throw ValidationException::withMessages(['payment' => 'Payment verification is unavailable.']);
        }
        try {
            $this->gateway->verifyPaymentSignature((string) $attempt->paymentAccount->public_key, $secret, [
                'razorpay_order_id' => (string) $attempt->provider_order_id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);
            $payment = $this->gateway->fetchPayment((string) $attempt->paymentAccount->public_key, $secret, $paymentId);
        } catch (Throwable) {
            throw ValidationException::withMessages(['payment' => 'Razorpay payment verification failed.']);
        }
        if ((string) ($payment['id'] ?? '') !== $paymentId
            || (string) ($payment['order_id'] ?? '') !== (string) $attempt->provider_order_id
            || (int) ($payment['amount'] ?? -1) !== (int) $attempt->amount_minor
            || strtoupper((string) ($payment['currency'] ?? '')) !== strtoupper($attempt->currency)
            || (string) ($payment['status'] ?? '') !== 'captured') {
            throw ValidationException::withMessages(['payment' => 'Razorpay payment details did not match the order.']);
        }

        return DB::transaction(function () use ($attempt, $paymentId): Order {
            $lockedAttempt = PaymentAttempt::query()->whereKey($attempt)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($lockedAttempt->order_id)->lockForUpdate()->firstOrFail();
            if ($lockedAttempt->status === PaymentAttempt::PAID || $order->payment_status === Order::PAYMENT_PAID) {
                return $order;
            }
            $lockedAttempt->forceFill(['status' => PaymentAttempt::PAID, 'provider_payment_id' => $paymentId, 'paid_at' => now(), 'failure_code' => null, 'failure_message' => null])->save();
            $order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'amount_paid' => number_format($lockedAttempt->amount_minor / 100, 2, '.', ''), 'payment_reference' => $paymentId])->save();

            return $order;
        });
    }

    public function recordClientOutcome(PaymentAttempt $attempt, string $status): void
    {
        if (in_array($status, [PaymentAttempt::FAILED, PaymentAttempt::ABANDONED], true)) {
            PaymentAttempt::query()->whereKey($attempt)->where('status', '!=', PaymentAttempt::PAID)->update(['status' => $status, 'failed_at' => $status === PaymentAttempt::FAILED ? now() : null]);
        }
    }

    public function minorUnits(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw ValidationException::withMessages(['payment' => 'Invalid order total.']);
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
