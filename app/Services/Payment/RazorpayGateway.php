<?php

namespace App\Services\Payment;

interface RazorpayGateway
{
    public function createOrder(string $keyId, string $secret, array $attributes): array;

    public function verifyPaymentSignature(string $keyId, string $secret, array $attributes): void;

    public function fetchPayment(string $keyId, string $secret, string $paymentId): array;
}
