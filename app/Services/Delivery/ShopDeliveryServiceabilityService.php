<?php

namespace App\Services\Delivery;

use App\Models\PostalCode;
use App\Models\PostalCodeRestriction;
use App\Models\Shop;
use App\Services\Merchant\ShopSettingsInitializer;
use App\Services\Merchant\ShopSettingsService;
use App\Services\PostalCodeServiceabilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShopDeliveryServiceabilityService
{
    public const SCOPE_LOCAL_ONLY = 'local_only';
    public const SCOPE_NATIONWIDE = 'nationwide';

    private const CUSTOMER_UNAVAILABLE_MESSAGE = 'Delivery is not available to this PIN code.';

    public function __construct(
        private readonly ShopSettingsService $settings,
        private readonly ShopSettingsInitializer $initializer,
        private readonly PostalCodeServiceabilityService $restrictions,
    ) {
    }

    /**
     * @return array{
     *     serviceable: bool,
     *     code: string|null,
     *     reason: string|null,
     *     message: string|null,
     *     delivery_scope: string,
     *     destination_postal_code: string|null,
     *     destination_location: array{state: string, district: string}|null,
     *     shop_location: array{state: string, district: string}|null,
     *     restriction: PostalCodeRestriction|null
     * }
     */
    public function check(Shop $shop, ?string $postalCode): array
    {
        $shopId = (int) $shop->getKey();
        $this->initializer->initialize($shopId);

        $scope = $this->deliveryScope($shop);

        if (! (bool) $this->settings->get($shopId, 'fulfillment', 'delivery_enabled', true)) {
            return $this->unavailable('delivery_disabled', 'Delivery is not enabled for this shop.', $scope, $postalCode);
        }

        $postalCode = $this->normalizePostalCode($postalCode);
        if ($postalCode === null) {
            return $this->unavailable('invalid_pin', self::CUSTOMER_UNAVAILABLE_MESSAGE, $scope, null);
        }

        $destination = $this->postalLocation($postalCode, shippingRequired: true);
        if ($destination === null) {
            return $this->unavailable('pin_not_shipping_enabled', self::CUSTOMER_UNAVAILABLE_MESSAGE, $scope, $postalCode);
        }

        $shopLocation = null;

        if ($scope === self::SCOPE_LOCAL_ONLY) {
            $shopPostalCode = $this->normalizePostalCode($shop->pincode);
            $shopLocation = $shopPostalCode !== null
                ? $this->postalLocation($shopPostalCode, shippingRequired: false)
                : null;

            if ($shopLocation === null || ! $this->sameLocation($shopLocation, $destination)) {
                return $this->unavailable(
                    'outside_local_scope',
                    self::CUSTOMER_UNAVAILABLE_MESSAGE,
                    $scope,
                    $postalCode,
                    destinationLocation: $destination,
                    shopLocation: $shopLocation,
                );
            }
        }

        $restriction = $this->restrictions->check($postalCode, (int) $shop->merchant_id, $shopId);
        if (! $restriction['serviceable']) {
            return $this->unavailable(
                'postal_restricted',
                $restriction['reason'] ?: self::CUSTOMER_UNAVAILABLE_MESSAGE,
                $scope,
                $postalCode,
                destinationLocation: $destination,
                restriction: $restriction['restriction'],
            );
        }

        return [
            'serviceable' => true,
            'code' => null,
            'reason' => null,
            'message' => null,
            'delivery_scope' => $scope,
            'destination_postal_code' => $postalCode,
            'destination_location' => $destination,
            'shop_location' => $shopLocation,
            'restriction' => null,
        ];
    }

    private function deliveryScope(Shop $shop): string
    {
        $scope = (string) $this->settings->get(
            (int) $shop->getKey(),
            'fulfillment',
            'delivery_scope',
            $this->initializer->defaults()['fulfillment']['delivery_scope']['value'],
        );

        return $scope === self::SCOPE_NATIONWIDE ? self::SCOPE_NATIONWIDE : self::SCOPE_LOCAL_ONLY;
    }

    private function normalizePostalCode(?string $postalCode): ?string
    {
        $postalCode = trim((string) $postalCode);

        return preg_match('/^\d{6}$/', $postalCode) === 1 ? $postalCode : null;
    }

    /**
     * @return array{state: string, district: string}|null
     */
    private function postalLocation(string $postalCode, bool $shippingRequired): ?array
    {
        $rows = PostalCode::query()
            ->active()
            ->where('postal_code', $postalCode)
            ->when($shippingRequired, fn ($query) => $query->shippingEnabled())
            ->get(['state', 'district']);

        if ($rows->isEmpty()) {
            return null;
        }

        $locations = $rows
            ->map(fn (PostalCode $row): array => [
                'state' => $this->normalizeLocationPart($row->state),
                'district' => $this->normalizeLocationPart($row->district),
            ])
            ->filter(fn (array $location): bool => $location['state'] !== '' && $location['district'] !== '')
            ->unique(fn (array $location): string => $location['state'].'|'.$location['district'])
            ->values();

        if ($locations->count() !== 1) {
            return null;
        }

        /** @var Collection<int, array{state: string, district: string}> $locations */
        return $locations->first();
    }

    private function normalizeLocationPart(?string $value): string
    {
        return Str::of((string) $value)
            ->squish()
            ->lower()
            ->toString();
    }

    /**
     * @param array{state: string, district: string} $left
     * @param array{state: string, district: string} $right
     */
    private function sameLocation(array $left, array $right): bool
    {
        return $left['state'] === $right['state']
            && $left['district'] === $right['district'];
    }

    /**
     * @return array{serviceable: false, code: string, reason: string, message: string, delivery_scope: string, destination_postal_code: string|null, destination_location: array{state: string, district: string}|null, shop_location: array{state: string, district: string}|null, restriction: PostalCodeRestriction|null}
     */
    private function unavailable(
        string $code,
        string $reason,
        string $scope,
        ?string $postalCode,
        ?array $destinationLocation = null,
        ?array $shopLocation = null,
        ?PostalCodeRestriction $restriction = null,
    ): array {
        return [
            'serviceable' => false,
            'code' => $code,
            'reason' => $reason,
            'message' => $reason,
            'delivery_scope' => $scope,
            'destination_postal_code' => $postalCode,
            'destination_location' => $destinationLocation,
            'shop_location' => $shopLocation,
            'restriction' => $restriction,
        ];
    }
}
