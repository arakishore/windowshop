<?php

namespace App\Services\Customer;

use App\Models\CustomerAddress;
use App\Models\LocCity;
use App\Models\LocState;
use App\Services\Checkout\CheckoutPostalCodeLookupService;
use App\Services\Storefront\StorefrontCountryResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorefrontCustomerAddressValidator
{
    public function __construct(
        private readonly CheckoutPostalCodeLookupService $postalLookup,
        private readonly StorefrontCountryResolver $countries,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'address_type' => ['nullable', Rule::in(['Home', 'Work', 'Other'])],
            'address_label' => ['nullable', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_mobile_country_code' => ['nullable', 'string', 'max:10'],
            'recipient_mobile' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:190'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'state_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'city_name' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in([CustomerAddress::STATUS_ACTIVE])],
        ]) + [
            'status' => CustomerAddress::STATUS_ACTIVE,
        ];
        $data['label'] = $this->resolvedLabel($request, $data);
        $data['recipient_mobile_country_code'] = $data['recipient_mobile_country_code'] ?? '+91';

        $country = $this->countries->defaultCountry();
        $data['country_id'] = $country->getKey();

        if ($this->countries->isIndia($country)) {
            $lookup = $this->postalLookup->lookupIndiaPin((string) ($data['postal_code'] ?? ''));

            if (! $lookup['valid']) {
                throw ValidationException::withMessages([
                    'postal_code' => 'Please enter a valid Indian PIN code.',
                ]);
            }

            $location = $this->postalLookup->resolveIndiaAddressLocation((string) $lookup['postal_code']);
            $data['country_id'] = $location['country_id'];
            $data['state_id'] = $this->resolveStateId(
                (int) $data['country_id'],
                $location['state_text'] ?? null,
                $location['state_id'] !== null ? (int) $location['state_id'] : null,
            );
            $cityName = trim((string) ($data['city_name'] ?? $location['city_text'] ?? ''));

            if ($data['state_id'] === null) {
                throw ValidationException::withMessages([
                    'state_id' => 'State could not be resolved from this PIN code.',
                ]);
            }

            if ($cityName === '') {
                throw ValidationException::withMessages([
                    'city_name' => 'Please enter a city.',
                ]);
            }

            $data['city_id'] = $this->resolveCityId(
                (int) $data['country_id'],
                (int) $data['state_id'],
                $cityName,
                $location['city_id'] !== null ? (int) $location['city_id'] : null,
            );
            $data['postal_code'] = $lookup['postal_code'];

            return $data;
        }

        if (($data['state_id'] ?? null) !== null) {
            $stateExists = DB::table('loc_states')
                ->where('id', (int) $data['state_id'])
                ->where('country_id', (int) $data['country_id'])
                ->where('status', true)
                ->whereNull('deleted_at')
                ->exists();

            if (! $stateExists) {
                throw ValidationException::withMessages([
                    'state_id' => 'Please select a valid state for the selected country.',
                ]);
            }
        }

        if (($data['city_id'] ?? null) !== null) {
            $cityExists = DB::table('loc_cities')
                ->where('id', (int) $data['city_id'])
                ->where('country_id', (int) $data['country_id'])
                ->when(($data['state_id'] ?? null) !== null, fn ($query) => $query->where('state_id', (int) $data['state_id']))
                ->whereNull('deleted_at')
                ->exists();

            if (! $cityExists) {
                throw ValidationException::withMessages([
                    'city_id' => 'Please select a valid city for the selected state.',
                ]);
            }
        }

        return $data;
    }

    private function resolveStateId(int $countryId, mixed $stateName, ?int $fallbackStateId): ?int
    {
        $stateName = trim((string) $stateName);

        if ($stateName === '') {
            return $fallbackStateId;
        }

        $existing = LocState::query()
            ->where('country_id', $countryId)
            ->where('name', $stateName)
            ->whereNull('deleted_at')
            ->first();

        if ($existing instanceof LocState) {
            return $existing->getKey();
        }

        $countryCode = (string) DB::table('loc_countries')->where('id', $countryId)->value('iso2');

        return (int) DB::table('loc_states')->insertGetId([
            'name' => $stateName,
            'country_id' => $countryId,
            'country_code' => $countryCode !== '' ? $countryCode : 'IN',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolvedLabel(Request $request, array $data): string
    {
        $type = (string) ($data['address_type'] ?? '');
        $label = trim((string) ($data['label'] ?? ''));

        if ($type === 'Home' || $type === 'Work') {
            return $type;
        }

        if ($type === 'Other') {
            $label = trim((string) ($data['address_label'] ?? ''));
        }

        if ($label === '') {
            throw ValidationException::withMessages([
                'address_label' => 'Please enter an address label.',
            ]);
        }

        return $label;
    }

    private function resolveCityId(int $countryId, ?int $stateId, mixed $cityName, ?int $fallbackCityId): ?int
    {
        $cityName = trim((string) $cityName);

        if ($cityName === '' || $stateId === null) {
            return $fallbackCityId;
        }

        $existing = LocCity::query()
            ->where('country_id', $countryId)
            ->where('state_id', $stateId)
            ->where('name', $cityName)
            ->whereNull('deleted_at')
            ->first();

        if ($existing instanceof LocCity) {
            return $existing->getKey();
        }

        return (int) DB::table('loc_cities')->insertGetId([
            'name' => $cityName,
            'country_id' => $countryId,
            'state_id' => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
