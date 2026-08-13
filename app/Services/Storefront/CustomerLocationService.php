<?php

namespace App\Services\Storefront;

use App\Models\PostalCode;
use Illuminate\Http\Request;

class CustomerLocationService
{
    public const SESSION_KEY = 'storefront.shopping_postal_code';
    public const COOKIE_NAME = 'windowshop_postal_code';
    public const COOKIE_MINUTES = 60 * 24 * 30;
    public const MAX_DETECTED_DISTANCE_KM = 100.0;

    public function postalCode(?Request $request = null): ?string
    {
        $request ??= request();

        $sessionPostalCode = $this->normalize($request->session()->get(self::SESSION_KEY));

        if ($sessionPostalCode !== null) {
            return $sessionPostalCode;
        }

        return $this->normalize($request->cookie(self::COOKIE_NAME));
    }

    public function hasPostalCode(?Request $request = null): bool
    {
        return $this->postalCode($request) !== null;
    }

    public function store(Request $request, string $postalCode): string
    {
        $postalCode = $this->normalize($postalCode) ?? $postalCode;

        $request->session()->put(self::SESSION_KEY, $postalCode);

        return $postalCode;
    }

    public function postalCodeRecord(string $postalCode): ?PostalCode
    {
        $postalCode = $this->normalize($postalCode);

        if ($postalCode === null) {
            return null;
        }

        return PostalCode::query()
            ->active()
            ->where('postal_code', $postalCode)
            ->orderByDesc('shipping_enabled')
            ->orderBy('office_name')
            ->first();
    }

    /**
     * @return array{postal_code: string, locality: ?string, district: ?string, state: ?string, distance_km: float}|null
     */
    public function resolveNearestPostalCode(float $latitude, float $longitude): ?array
    {
        $nearest = PostalCode::query()
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['postal_code', 'office_name', 'district', 'state', 'latitude', 'longitude'])
            ->map(function (PostalCode $postalCode) use ($latitude, $longitude): array {
                return [
                    'postal_code' => $postalCode->postal_code,
                    'locality' => $postalCode->office_name,
                    'district' => $postalCode->district,
                    'state' => $postalCode->state,
                    'distance_km' => $this->haversineDistanceKm(
                        $latitude,
                        $longitude,
                        (float) $postalCode->latitude,
                        (float) $postalCode->longitude,
                    ),
                ];
            })
            ->sortBy('distance_km')
            ->first();

        if ($nearest === null || $nearest['distance_km'] > self::MAX_DETECTED_DISTANCE_KM) {
            return null;
        }

        $nearest['distance_km'] = round($nearest['distance_km'], 2);

        return $nearest;
    }

    public function normalize(mixed $postalCode): ?string
    {
        $postalCode = trim((string) $postalCode);

        return preg_match('/^\d{6}$/', $postalCode) === 1 ? $postalCode : null;
    }

    private function haversineDistanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371.0088;
        $latDelta = deg2rad($toLatitude - $fromLatitude);
        $lonDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($lonDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
