<?php

namespace App\Services\Storefront;

use Illuminate\Http\Request;

class CustomerLocationService
{
    public const SESSION_KEY = 'storefront.shopping_postal_code';
    public const COOKIE_NAME = 'windowshop_postal_code';
    public const COOKIE_MINUTES = 60 * 24 * 30;

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

    public function normalize(mixed $postalCode): ?string
    {
        $postalCode = trim((string) $postalCode);

        return preg_match('/^\d{6}$/', $postalCode) === 1 ? $postalCode : null;
    }
}
