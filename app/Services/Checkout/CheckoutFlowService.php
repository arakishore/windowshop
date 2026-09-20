<?php

namespace App\Services\Checkout;

use App\Services\Cart\CartPageService;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\Request;

class CheckoutFlowService
{
    public const INTENT_SESSION_KEY = 'storefront_checkout_intent';
    public const CHECKOUT_ROUTE = 'storefront.checkout';
    public const ADDRESS_ROUTE = self::CHECKOUT_ROUTE;
    public const SELECTED_SHOP_SESSION_KEY = 'storefront.checkout.selected_shop_id';

    public function __construct(
        private readonly CartPageService $cartPage,
        private readonly StorefrontCustomerContext $customerContext,
    ) {
    }

    public function rememberIntent(Request $request): void
    {
        $request->session()->put(self::INTENT_SESSION_KEY, true);
        $request->session()->put('url.intended', route(self::CHECKOUT_ROUTE));
    }

    public function forgetIntent(Request $request): void
    {
        $request->session()->forget(self::INTENT_SESSION_KEY);
    }

    public function hasIntent(Request $request): bool
    {
        return (bool) $request->session()->get(self::INTENT_SESSION_KEY, false);
    }

    public function hasCartItems(Request $request): bool
    {
        return ! (bool) $this->cartPage->pageData($request)['is_empty'];
    }

    public function selectShop(Request $request, int $shopId): bool
    {
        $cart = $this->cartPage->currentCart($request);
        $valid = $shopId > 0 && $cart?->items->contains(
            fn ($item): bool => (int) $item->shop_id === $shopId,
        );

        if (! $valid) {
            return false;
        }

        $request->session()->put(self::SELECTED_SHOP_SESSION_KEY, $shopId);

        return true;
    }

    public function selectedShopId(Request $request): ?int
    {
        $cart = $this->cartPage->currentCart($request);
        $shopIds = $cart?->items->pluck('shop_id')->map(fn ($id): int => (int) $id)->unique()->values() ?? collect();
        $selected = (int) $request->session()->get(self::SELECTED_SHOP_SESSION_KEY, 0);

        if ($selected > 0 && $shopIds->contains($selected)) {
            return $selected;
        }

        if ($shopIds->count() === 1) {
            $selected = (int) $shopIds->first();
            $request->session()->put(self::SELECTED_SHOP_SESSION_KEY, $selected);

            return $selected;
        }

        $request->session()->forget(self::SELECTED_SHOP_SESSION_KEY);

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function checkoutData(Request $request): ?array
    {
        $shopId = $this->selectedShopId($request);

        return $shopId === null ? null : $this->cartPage->pageDataForShop($request, $shopId);
    }

    public function hasCheckoutItems(Request $request): bool
    {
        $data = $this->checkoutData($request);

        return is_array($data) && ! (bool) ($data['is_empty'] ?? true);
    }

    public function clearSelectedShop(Request $request): void
    {
        $request->session()->forget(self::SELECTED_SHOP_SESSION_KEY);
    }

    public function isCustomer(Request $request): bool
    {
        return $this->customerContext->user($request) !== null;
    }
}
