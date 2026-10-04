<?php

namespace App\Services\Checkout;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

class UpiPaymentDataService
{
    public function reference(): string
    {
        return 'WS'.strtoupper(bin2hex(random_bytes(8)));
    }

    /** @return array{uri:string, qr_url:string, amount:string, reference:string} */
    public function build(string $upiId, string $payeeName, string $amount, ?string $reference = null): array
    {
        $normalizedAmount = number_format((float) $amount, 2, '.', '');
        $reference ??= $this->reference();
        $uri = 'upi://pay?'.http_build_query([
            'pa' => trim($upiId),
            'pn' => trim($payeeName),
            'tr' => $reference,
            'am' => $normalizedAmount,
            'cu' => 'INR',
        ], '', '&', PHP_QUERY_RFC3986);
        $result = (new Builder(writer: new SvgWriter(), data: $uri, size: 360, margin: 10))->build();

        return ['uri' => $uri, 'qr_url' => $result->getDataUri(), 'amount' => $normalizedAmount, 'reference' => $reference];
    }
}
