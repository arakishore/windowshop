<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\CheckoutPageService;
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
        private readonly CheckoutPageService $checkoutPage,
    ) {
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.dashboard', $this->accountViewData($customer, [
            'orderCount' => $this->orderCount($customer),
            'addressCount' => $this->checkoutPage->addressesFor($customer)->count(),
        ]));
    }

    public function profile(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.profile', $this->accountViewData($customer));
    }

    public function addresses(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.addresses', $this->accountViewData($customer, [
            'addresses' => $this->checkoutPage->addressesFor($customer),
        ]));
    }

    public function orders(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.orders', $this->accountViewData($customer));
    }

    public function wishlist(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.wishlist', $this->accountViewData($customer));
    }

    private function customerOrRedirect(Request $request): User|RedirectResponse
    {
        $customer = $this->customerContext->user($request);

        return $customer instanceof User
            ? $customer
            : redirect()->route('storefront.login');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function accountViewData(User $customer, array $data = []): array
    {
        return array_merge([
            'customer' => $customer,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ], $data);
    }

    private function orderCount(User $customer): int
    {
        $globalCustomer = $customer->customer;

        if (! $globalCustomer instanceof Customer) {
            return 0;
        }

        return Order::query()
            ->where('customer_id', $globalCustomer->getKey())
            ->count();
    }
}
