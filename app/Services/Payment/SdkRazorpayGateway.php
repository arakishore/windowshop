<?php

namespace App\Services\Payment;

use Razorpay\Api\Api;

class SdkRazorpayGateway implements RazorpayGateway
{
    public function createOrder(string $keyId, string $secret, array $attributes): array
    {
        return (new Api($keyId, $secret))->order->create($attributes)->toArray();
    }

    public function verifyPaymentSignature(string $keyId, string $secret, array $attributes): void
    {
        (new Api($keyId, $secret))->utility->verifyPaymentSignature($attributes);
    }

    public function fetchPayment(string $keyId, string $secret, string $paymentId): array
    {
        return (new Api($keyId, $secret))->payment->fetch($paymentId)->toArray();
    }
}
