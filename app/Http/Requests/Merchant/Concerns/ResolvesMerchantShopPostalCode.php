<?php

namespace App\Http\Requests\Merchant\Concerns;

use App\Services\Checkout\CheckoutPostalCodeLookupService;
use Illuminate\Validation\Validator;

trait ResolvesMerchantShopPostalCode
{
    protected function resolveMerchantShopPostalCode(): void
    {
        $lookup = app(CheckoutPostalCodeLookupService::class);
        $countryId = $this->integer('country_id') ?: null;
        $postalCode = trim((string) $this->input('pincode', ''));

        if ($countryId === null || $postalCode === '') {
            return;
        }

        if (! $lookup->countryIsIndia($countryId)) {
            return;
        }

        $result = $lookup->lookupIndiaPin($postalCode);

        if (! $result['valid']) {
            return;
        }

        $this->merge([
            'pincode' => $result['postal_code'],
            'country_id' => $result['country_id'],
            'state_id' => $result['state_id'],
            'city_id' => $result['city_id'],
            'latitude' => $result['latitude'] ?? $this->input('latitude'),
            'longitude' => $result['longitude'] ?? $this->input('longitude'),
        ]);
    }

    protected function validateMerchantShopPostalCode(Validator $validator): void
    {
        $lookup = app(CheckoutPostalCodeLookupService::class);
        $countryId = $this->integer('country_id') ?: null;
        $postalCode = trim((string) $this->input('pincode', ''));

        if ($countryId !== null
            && $postalCode !== ''
            && $lookup->countryIsIndia($countryId)
            && ! $lookup->lookupIndiaPin($postalCode)['valid']) {
            $validator->errors()->add('pincode', 'Please enter a valid Indian PIN code.');
        }
    }
}
