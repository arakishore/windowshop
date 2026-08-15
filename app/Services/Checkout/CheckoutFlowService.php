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

    public function isCustomer(Request $request): bool
    {
        return $this->customerContext->user($request) !== null;
    }
}
