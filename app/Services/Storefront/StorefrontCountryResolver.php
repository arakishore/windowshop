<?php

namespace App\Services\Storefront;

use App\Models\LocCountry;
use App\Services\System\SystemSettingService;
use Illuminate\Support\Facades\Log;

class StorefrontCountryResolver
{
    public function __construct(
        private readonly SystemSettingService $settings,
    ) {
    }

    public function defaultCountry(): LocCountry
    {
        $code = $this->defaultCountryCode();

        $country = LocCountry::query()
            ->where('iso2', $code)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->first();

        if ($country instanceof LocCountry) {
            return $country;
        }

        Log::error('Storefront default country could not be resolved.', [
            'default_country_code' => $code,
        ]);

        throw new \RuntimeException("Storefront default country [{$code}] is not configured.");
    }

    public function defaultCountryCode(): string
    {
        $code = $this->settings->get('default_country_code');

        if (! is_string($code) || trim($code) === '') {
            $code = (string) config('location.default_country_code', '');
        }

        $code = strtoupper(trim($code));

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            Log::error('Storefront default country code is invalid.', [
                'default_country_code' => $code,
            ]);

            throw new \RuntimeException('Storefront default country code is invalid.');
        }

        return $code;
    }

    public function isIndia(LocCountry $country): bool
    {
        return strtoupper((string) $country->iso2) === 'IN';
    }
}
