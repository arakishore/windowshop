<?php

namespace App\Services\Payment;

use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class RazorpayWebhookService
{
    public function __construct(
        private readonly PaymentAccountService $accounts,
        private readonly RazorpayGateway $gateway,
        private readonly RazorpayPaymentService $payments,
    ) {}

    /** @return array{status: int, disposition: string} */
    public function handle(PaymentAccount $account, string $rawBody, ?string $signature, ?string $providerEventId): array
    {
        $secret = $this->accounts->webhookSecret($account);
        if (blank($secret)) {
            return ['status' => 503, 'disposition' => 'webhook_not_configured'];
        }
        if (blank($signature)) {
            return ['status' => 400, 'disposition' => 'missing_signature'];
        }
        try {
            $this->gateway->verifyWebhookSignature($rawBody, $signature, $secret);
        } catch (Throwable) {
            return ['status' => 400, 'disposition' => 'invalid_signature'];
        }
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['status' => 400, 'disposition' => 'malformed_payload'];
        }
        if (! is_array($payload) || ! is_string($payload['event'] ?? null)) {
            return ['status' => 400, 'disposition' => 'malformed_payload'];
        }

        $eventType = $payload['event'];
        $payment = data_get($payload, 'payload.payment.entity');
        $payment = is_array($payment) ? $payment : [];
        $eventId = filled($providerEventId) ? mb_substr((string) $providerEventId, 0, 191) : 'sha256:'.hash('sha256', $rawBody);
        $identity = ['payment_account_id' => $account->getKey(), 'provider' => RazorpayPaymentService::PROVIDER, 'provider_event_id' => $eventId];
        $receipt = PaymentWebhookEvent::query()->where($identity)->first();
        if ($receipt?->processed_at) {
            return ['status' => 200, 'disposition' => 'duplicate'];
        }
        if (! $receipt) {
            try {
                $receipt = PaymentWebhookEvent::query()->create([...$identity,
                    'event_type' => mb_substr($eventType, 0, 100),
                    'provider_payment_id' => $this->value($payment['id'] ?? null),
                    'provider_order_id' => $this->value($payment['order_id'] ?? null),
                    'amount_minor' => is_int($payment['amount'] ?? null) ? $payment['amount'] : null,
                    'currency' => is_string($payment['currency'] ?? null) ? mb_substr(strtoupper($payment['currency']), 0, 3) : null,
                    'disposition' => 'received', 'payload_hash' => hash('sha256', $rawBody), 'received_at' => now(),
                ]);
            } catch (QueryException) {
                $receipt = PaymentWebhookEvent::query()->where($identity)->firstOrFail();
                if ($receipt->processed_at) {
                    return ['status' => 200, 'disposition' => 'duplicate'];
                }
            }
        }

        if (! in_array($eventType, ['payment.captured', 'payment.failed'], true)) {
            return $this->finish($receipt, 'ignored_unsupported');
        }
        $paymentId = $this->value($payment['id'] ?? null);
        $providerOrderId = $this->value($payment['order_id'] ?? null);
        if (! $paymentId || ! $providerOrderId) {
            return $this->finish($receipt, 'rejected', 'missing_payment_binding', 422);
        }
        $attempt = PaymentAttempt::query()->where('payment_account_id', $account->getKey())
            ->where('provider', RazorpayPaymentService::PROVIDER)->where('provider_order_id', $providerOrderId)->first();
        if (! $attempt) {
            return $this->retryable($receipt, 'unknown_provider_order');
        }
        $order = $attempt->order;
        if (! $order || (int) $order->merchant_id !== (int) $account->merchant_id || (int) $order->shop_id !== (int) $attempt->shop_id) {
            return $this->finish($receipt, 'rejected', 'account_scope_mismatch', 200);
        }
        if ($eventType === 'payment.failed') {
            return $this->recordFailure($receipt, $attempt, $payment, $paymentId);
        }
        try {
            $result = $this->payments->reconcileCaptured($attempt, $paymentId);
        } catch (ValidationException $exception) {
            return $this->finish($receipt, 'rejected', mb_substr($exception->getMessage(), 0, 191), 200);
        }

        return $this->finish($receipt, $result['disposition']);
    }

    private function recordFailure(PaymentWebhookEvent $receipt, PaymentAttempt $attempt, array $payment, string $paymentId): array
    {
        if ((filled($attempt->provider_payment_id) && ! hash_equals((string) $attempt->provider_payment_id, $paymentId))
            || (string) ($payment['status'] ?? '') !== 'failed'
            || (int) ($payment['amount'] ?? -1) !== $attempt->amount_minor
            || strtoupper((string) ($payment['currency'] ?? '')) !== strtoupper($attempt->currency)) {
            return $this->finish($receipt, 'rejected', 'payment_details_mismatch', 200);
        }
        $changed = DB::transaction(function () use ($attempt, $payment, $paymentId): bool {
            $locked = PaymentAttempt::query()->whereKey($attempt)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [PaymentAttempt::PAID, PaymentAttempt::FAILED], true)) {
                return false;
            }
            $locked->forceFill([
                'status' => PaymentAttempt::FAILED, 'provider_payment_id' => $locked->provider_payment_id ?: $paymentId,
                'failed_at' => now(), 'failure_code' => $this->value(data_get($payment, 'error_code')),
                'failure_message' => mb_substr((string) data_get($payment, 'error_description', 'Razorpay reported payment failure.'), 0, 1000),
            ])->save();

            return true;
        });

        $status = $attempt->fresh()->status;

        return $this->finish($receipt, $changed ? 'processed' : ($status === PaymentAttempt::PAID ? 'already_paid' : 'already_failed'));
    }

    private function finish(PaymentWebhookEvent $receipt, string $disposition, ?string $reason = null, int $status = 200): array
    {
        $receipt->forceFill(['disposition' => $disposition, 'failure_reason' => $reason, 'processed_at' => now()])->save();

        return ['status' => $status, 'disposition' => $disposition];
    }

    private function retryable(PaymentWebhookEvent $receipt, string $reason): array
    {
        $receipt->forceFill(['disposition' => 'retryable', 'failure_reason' => $reason])->save();

        return ['status' => 503, 'disposition' => 'retryable'];
    }

    private function value(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 191) : null;
    }
}
