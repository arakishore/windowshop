<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentAttempt;

class PaymentDisplayService
{
    /**
     * @return array{provider: string, provider_label: string, payment_id: string, gateway_order_id: string|null, bank_rrn: string|null}|null
     */
    public function paidGatewayDetails(Order $order): ?array
    {
        $attempts = $order->relationLoaded('paymentAttempts')
            ? $order->paymentAttempts
            : $order->paymentAttempts()->get();
        $attempt = $attempts
            ->filter(fn (PaymentAttempt $attempt): bool => $attempt->status === PaymentAttempt::PAID && filled($attempt->provider_payment_id))
            ->sortByDesc(fn (PaymentAttempt $attempt): string => sprintf('%020d:%020d', $attempt->paid_at?->getTimestamp() ?? 0, $attempt->getKey()))
            ->first();

        if (! $attempt instanceof PaymentAttempt) {
            return null;
        }

        return [
            'provider' => $attempt->provider,
            'provider_label' => $this->providerLabel($attempt->provider),
            'payment_id' => (string) $attempt->provider_payment_id,
            'gateway_order_id' => filled($attempt->provider_order_id) ? (string) $attempt->provider_order_id : null,
            'bank_rrn' => filled(data_get($attempt->metadata, 'bank_rrn')) ? (string) data_get($attempt->metadata, 'bank_rrn') : null,
        ];
    }

    public function providerLabel(string $provider): string
    {
        return match (strtolower($provider)) {
            'razorpay' => 'Razorpay',
            'payu' => 'PayU',
            'phonepe' => 'PhonePe',
            'paytm' => 'Paytm',
            default => str($provider)->headline()->toString(),
        };
    }
}
