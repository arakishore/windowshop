<?php

namespace App\Services\Promotion\Coupons;

use Illuminate\Http\Request;

class CouponSessionStore
{
    public const SESSION_KEY = 'storefront.applied_coupons';

    /**
     * @return array<int, string>
     */
    public function all(Request $request): array
    {
        $stored = $request->session()->get(self::SESSION_KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        $coupons = [];
        foreach ($stored as $shopId => $code) {
            $shopId = (int) $shopId;
            if ($shopId > 0 && is_string($code) && $code !== '') {
                $coupons[$shopId] = $code;
            }
        }

        return $coupons;
    }

    public function get(Request $request, int $shopId): ?string
    {
        return $this->all($request)[$shopId] ?? null;
    }

    public function put(Request $request, int $shopId, string $code): void
    {
        $coupons = $this->all($request);
        $coupons[$shopId] = $code;
        $request->session()->put(self::SESSION_KEY, $coupons);
    }

    public function forget(Request $request, int $shopId): void
    {
        $coupons = $this->all($request);
        unset($coupons[$shopId]);

        if ($coupons === []) {
            $request->session()->forget(self::SESSION_KEY);
            return;
        }

        $request->session()->put(self::SESSION_KEY, $coupons);
    }
}
