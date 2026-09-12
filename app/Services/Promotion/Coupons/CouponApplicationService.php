<?php

namespace App\Services\Promotion\Coupons;

use App\Models\Cart;
use App\Services\Cart\CartPageService;
use App\Services\Cart\CartResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponApplicationService
{
    public function __construct(
        private readonly CartResolver $carts,
        private readonly CartPageService $cartPage,
        private readonly CouponResolver $resolver,
        private readonly CouponSessionStore $store,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(Request $request, int $shopId, mixed $code): array
    {
        $this->assertCartContainsShop($request, $shopId);

        $resolution = $this->resolver->resolveForShop($shopId, $code);
        if (! $resolution->valid()) {
            if ($resolution->clearStoredState) {
                $this->store->forget($request, $shopId);
            }

            return [
                'success' => false,
                'message' => $resolution->message,
                'coupon' => $resolution->toArray(),
                ...$this->cartPage->payload($request),
            ];
        }

        $this->store->put($request, $shopId, (string) $resolution->code);
        $payload = $this->cartPage->payload($request);

        return [
            'success' => true,
            'message' => $this->shopCouponState($payload, $shopId)['message'] ?? $resolution->message,
            'coupon' => $this->shopCouponState($payload, $shopId) ?: $resolution->toArray(),
            ...$payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(Request $request, int $shopId): array
    {
        $this->assertCartContainsShop($request, $shopId);
        $this->store->forget($request, $shopId);

        return [
            'success' => true,
            'message' => 'Coupon removed.',
            'coupon' => [
                'status' => 'removed',
                'message' => 'Coupon removed.',
                'code' => null,
            ],
            ...$this->cartPage->payload($request),
        ];
    }

    private function assertCartContainsShop(Request $request, int $shopId): void
    {
        $cart = $this->carts->current($request);
        if (! $cart instanceof Cart || ! $cart->items()->where('shop_id', $shopId)->exists()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon is not valid for the items in your cart.',
            ]);
        }
    }

    private function shopCouponState(array $payload, int $shopId): ?array
    {
        foreach ($payload['shop_groups'] ?? [] as $group) {
            if ((int) ($group['shop_id'] ?? 0) === $shopId && isset($group['coupon']) && is_array($group['coupon'])) {
                return $group['coupon'];
            }
        }

        return null;
    }
}
