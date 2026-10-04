<?php

namespace Tests\Unit;

use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Services\Payment\PaymentAccountService;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PaymentFoundationTest extends TestCase
{
    public function test_payment_account_serialization_hides_encrypted_credentials(): void
    {
        $account = new PaymentAccount(['provider' => 'razorpay', 'mode' => 'test', 'public_key' => 'key']);
        $account->secret = Crypt::encryptString('secret');
        $account->webhook_secret = Crypt::encryptString('webhook');
        $this->assertArrayNotHasKey('secret', $account->toArray());
        $this->assertArrayNotHasKey('webhook_secret', json_decode($account->toJson(), true));
    }

    public function test_secure_accessor_decrypts_and_corrupted_ciphertext_fails_closed(): void
    {
        $service = app(PaymentAccountService::class);
        $account = new PaymentAccount;
        $account->secret = Crypt::encryptString('secret');
        $account->webhook_secret = Crypt::encryptString('webhook');
        $this->assertSame('secret', $service->secret($account));
        $this->assertSame('webhook', $service->webhookSecret($account));
        $account->secret = 'corrupted';
        $this->assertNull($service->secret($account));
    }

    public function test_payment_attempt_status_vocabulary_is_separate_from_order_status(): void
    {
        $this->assertSame(['created', 'pending', 'paid', 'failed', 'abandoned'], [
            PaymentAttempt::CREATED, PaymentAttempt::PENDING, PaymentAttempt::PAID,
            PaymentAttempt::FAILED, PaymentAttempt::ABANDONED,
        ]);
    }

    public function test_payment_account_supports_test_and_live_modes_without_fallback(): void
    {
        $test = new PaymentAccount(['provider' => 'razorpay', 'mode' => 'test']);
        $live = new PaymentAccount(['provider' => 'razorpay', 'mode' => 'live']);
        $this->assertSame('test', $test->mode);
        $this->assertSame('live', $live->mode);
        $this->assertNotSame($test->mode, $live->mode);
    }

    public function test_payment_attempt_amount_is_integer_minor_units(): void
    {
        $attempt = new PaymentAttempt(['amount_minor' => 99900, 'currency' => 'INR']);
        $this->assertSame(99900, $attempt->amount_minor);
        $this->assertIsInt($attempt->amount_minor);
    }

    public function test_payment_account_secret_is_not_mass_assignable(): void
    {
        $account = new PaymentAccount(['secret' => 'plaintext', 'webhook_secret' => 'plaintext']);
        $this->assertNull($account->secret);
        $this->assertNull($account->webhook_secret);
    }
}
