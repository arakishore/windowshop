<?php

namespace App\Services\Shared;

class MobileNumberNormalizer
{
    /**
     * @return array{country_code: string|null, mobile: string, mobile_normalized: string}
     */
    public function normalize(string $mobile, ?string $countryCode = null): array
    {
        $digits = $this->digits($mobile);
        $countryDigits = $this->digits((string) $countryCode);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($countryDigits === '') {
            [$countryDigits, $localMobile] = $this->splitWithoutCountryHint($digits);
        } else {
            $localMobile = $this->stripCountryHint($digits, $countryDigits);
        }

        $countryCode = $countryDigits !== '' ? '+'.$countryDigits : null;

        return [
            'country_code' => $countryCode,
            'mobile' => $localMobile,
            'mobile_normalized' => $countryDigits.$localMobile,
        ];
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitWithoutCountryHint(string $digits): array
    {
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return ['91', substr($digits, 1)];
        }

        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits) === 1) {
            return ['91', $digits];
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return ['91', substr($digits, 2)];
        }

        if (strlen($digits) > 10) {
            return [substr($digits, 0, -10), substr($digits, -10)];
        }

        return ['', $digits];
    }

    private function stripCountryHint(string $digits, string $countryDigits): string
    {
        if (str_starts_with($digits, $countryDigits)) {
            return substr($digits, strlen($countryDigits));
        }

        if ($countryDigits === '91' && strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }
}
