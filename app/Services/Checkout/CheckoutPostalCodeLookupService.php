<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\LocCity;
use App\Models\LocCountry;
use App\Models\LocState;
use App\Models\PostalCode;
use App\Models\Shop;
use App\Services\Delivery\ShopDeliveryServiceabilityService;
use App\Services\Storefront\StorefrontCountryResolver;
use Illuminate\Support\Collection;

class CheckoutPostalCodeLookupService
{
    public function __construct(
        private readonly StorefrontCountryResolver $countries,
        private readonly ShopDeliveryServiceabilityService $shopDeliveryServiceability,
    ) {
    }

    /**
     * @return array{valid: bool, postal_code: string, city: ?string, state: ?string, shipping_enabled: bool, shop_availability: array<int, array<string, mixed>>, message?: string}
     */
    public function lookupDefaultPostalCode(string $postalCode, ?Cart $cart = null): array
    {
        $country = $this->countries->defaultCountry();

        if (! $this->countries->isIndia($country)) {
            return [
                'valid' => false,
                'postal_code' => trim($postalCode),
                'city' => null,
                'state' => null,
                'shipping_enabled' => false,
                'shop_availability' => [],
                'message' => 'Postal-code lookup is not available for the storefront default country yet.',
            ];
        }

        return $this->lookupIndiaPin($postalCode, $cart);
    }

    /**
     * @return array{valid: bool, postal_code: string, city: ?string, state: ?string, shipping_enabled: bool, shop_availability: array<int, array<string, mixed>>, message?: string}
     */
    public function lookupIndiaPin(string $postalCode, ?Cart $cart = null): array
    {
        $postalCode = trim($postalCode);

        if (preg_match('/^\d{6}$/', $postalCode) !== 1) {
            return $this->invalid($postalCode);
        }

        $rows = PostalCode::query()
            ->active()
            ->where('postal_code', $postalCode)
            ->orderByDesc('shipping_enabled')
            ->orderBy('office_name')
            ->get();

        if ($rows->isEmpty()) {
            return $this->invalid($postalCode);
        }

        $selected = $rows->first();
        $shippingEnabled = $rows->contains(fn (PostalCode $row): bool => (bool) $row->shipping_enabled);

        return [
            'valid' => true,
            'postal_code' => $postalCode,
            'city' => $selected?->district,
            'state' => $selected?->state,
            'shipping_enabled' => $shippingEnabled,
            'shop_availability' => $this->shopAvailability($postalCode, $cart),
        ];
    }

    /**
     * @return array{country_id: ?int, state_id: ?int, city_id: ?int, city_text: ?string, state_text: ?string}
     */
    public function resolveIndiaAddressLocation(string $postalCode): array
    {
        $lookup = $this->lookupIndiaPin($postalCode);
        $country = $this->countries->defaultCountry();
        $state = null;
        $city = null;

        if ($lookup['valid'] && $this->countries->isIndia($country) && $lookup['state']) {
            $state = LocState::query()
                ->where('country_id', $country->getKey())
                ->where('name', $lookup['state'])
                ->where('status', true)
                ->whereNull('deleted_at')
                ->first();
        }

        if ($state instanceof LocState && $lookup['city']) {
            $city = LocCity::query()
                ->where('country_id', $country->getKey())
                ->where('state_id', $state->getKey())
                ->where('name', $lookup['city'])
                ->whereNull('deleted_at')
                ->first();
        }

        return [
            'country_id' => $country?->getKey(),
            'state_id' => $state?->getKey(),
            'city_id' => $city?->getKey(),
            'city_text' => $lookup['city'],
            'state_text' => $lookup['state'],
        ];
    }

    public function defaultCountry(): LocCountry
    {
        return $this->countries->defaultCountry();
    }

    public function defaultCountryIsIndia(): bool
    {
        return $this->countries->isIndia($this->countries->defaultCountry());
    }

    /**
     * @return Collection<int, LocCountry>
     */
    public function countries(): Collection
    {
        return LocCountry::query()
            ->whereNull('deleted_at')
            ->where('status', true)
            ->orderByRaw('case when iso2 = ? then 0 else 1 end', [$this->countries->defaultCountryCode()])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shopAvailability(string $postalCode, ?Cart $cart): array
    {
        $shops = $cart?->items
            ->map(fn ($item): ?Shop => $item->shop)
            ->filter()
            ->unique(fn (Shop $shop): int => (int) $shop->getKey())
            ->values() ?? collect();

        if ($shops->isEmpty()) {
            return [];
        }

        return $shops
            ->map(function (Shop $shop) use ($postalCode): array {
                $serviceability = $this->shopDeliveryServiceability->check($shop, $postalCode);

                return [
                    'shop_id' => $shop->getKey(),
                    'shop_name' => $shop->name,
                    'available' => (bool) $serviceability['serviceable'],
                    'reason' => $serviceability['serviceable'] ? null : $serviceability['reason'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{valid: false, postal_code: string, city: null, state: null, shipping_enabled: false, shop_availability: array<int, mixed>, message: string}
     */
    private function invalid(string $postalCode): array
    {
        return [
            'valid' => false,
            'postal_code' => $postalCode,
            'city' => null,
            'state' => null,
            'shipping_enabled' => false,
            'shop_availability' => [],
            'message' => 'Please enter a valid Indian PIN code.',
        ];
    }
}
