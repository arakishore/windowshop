<?php

namespace App\Services\Storefront;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductLocationSorter
{
    private const UNKNOWN_DISTANCE_KM = 999999.0;

    public function __construct(
        private readonly CustomerLocationService $location,
    ) {}

    public function apply(Builder $query, ?string $customerPostalCode, string $shopPostalCodeColumn): void
    {
        $customerPostalCode = $this->location->normalize($customerPostalCode);

        if ($customerPostalCode === null) {
            return;
        }

        $distanceByPostalCode = $this->distancesForCurrentShopPins($query, $customerPostalCode, $shopPostalCodeColumn);

        $query->orderByRaw(
            "CASE WHEN {$shopPostalCodeColumn} = ? THEN 0 ELSE 1 END",
            [$customerPostalCode],
        );

        $distanceSql = "CASE WHEN {$shopPostalCodeColumn} = ? THEN 0";
        $bindings = [$customerPostalCode];

        foreach ($distanceByPostalCode as $postalCode => $distanceKm) {
            if ($postalCode === $customerPostalCode) {
                continue;
            }

            $distanceSql .= " WHEN {$shopPostalCodeColumn} = ? THEN ?";
            $bindings[] = $postalCode;
            $bindings[] = $distanceKm;
        }

        $distanceSql .= ' ELSE ? END';
        $bindings[] = self::UNKNOWN_DISTANCE_KM;

        $query->orderByRaw($distanceSql, $bindings);
    }

    /**
     * @return array<string, float>
     */
    private function distancesForCurrentShopPins(Builder $query, string $customerPostalCode, string $shopPostalCodeColumn): array
    {
        $shopPins = $this->currentShopPins($query, $shopPostalCodeColumn);

        if ($shopPins === []) {
            return [];
        }

        $customerCoordinate = $this->representativeCoordinates([$customerPostalCode])->get($customerPostalCode);

        if ($customerCoordinate === null) {
            return [];
        }

        return $this->representativeCoordinates($shopPins)
            ->mapWithKeys(fn (array $coordinate, string $postalCode): array => [
                $postalCode => $this->haversineDistanceKm(
                    (float) $customerCoordinate['latitude'],
                    (float) $customerCoordinate['longitude'],
                    (float) $coordinate['latitude'],
                    (float) $coordinate['longitude'],
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function currentShopPins(Builder $query, string $shopPostalCodeColumn): array
    {
        return (clone $query)
            ->reorder()
            ->select(DB::raw("{$shopPostalCodeColumn} as shop_pin"))
            ->whereNotNull($shopPostalCodeColumn)
            ->where($shopPostalCodeColumn, '!=', '')
            ->distinct()
            ->pluck('shop_pin')
            ->map(fn ($postalCode): ?string => $this->location->normalize($postalCode))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $postalCodes
     * @return Collection<string, array{latitude: float, longitude: float}>
     */
    private function representativeCoordinates(array $postalCodes): Collection
    {
        return DB::table('postal_codes')
            ->select('postal_code')
            ->selectRaw('AVG(latitude) as latitude')
            ->selectRaw('AVG(longitude) as longitude')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereIn('postal_code', array_values(array_unique($postalCodes)))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('postal_code')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) $row->postal_code => [
                    'latitude' => (float) $row->latitude,
                    'longitude' => (float) $row->longitude,
                ],
            ]);
    }

    private function haversineDistanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371.0088;
        $latDelta = deg2rad($toLatitude - $fromLatitude);
        $lonDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($lonDelta / 2) ** 2;

        return round($earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 4);
    }
}
