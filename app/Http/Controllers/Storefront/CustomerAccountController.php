<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly StorefrontCustomerContext $customerContext,
    ) {
    }

    public function show(Request $request): View|RedirectResponse
    {
        $customer = $this->customerContext->user($request);

        if ($customer === null) {
            return redirect()->route('storefront.login');
        }

        return view('storefront.pages.customer-account', [
            'customer' => $customer,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }
}
