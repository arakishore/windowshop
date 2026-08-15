<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Checkout\CheckoutPageService;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutFlowService $checkout,
        private readonly CheckoutPageService $checkoutPage,
        private readonly NavigationService $navigation,
        private readonly StorefrontCustomerContext $customerContext,
    ) {
    }

    public function index(Request $request): RedirectResponse|View
    {
        if (! $this->checkout->hasCartItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        $this->checkout->rememberIntent($request);

        $customer = $this->customerContext->user($request);

        if ($customer === null) {
            return redirect()->route('storefront.checkout.account');
        }

        $this->checkout->forgetIntent($request);

        return view('storefront.pages.checkout', [
            ...$this->checkoutPage->pageData($request, $customer),
            'customer' => $customer,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function account(Request $request): View|RedirectResponse
    {
        if (! $this->checkout->hasCartItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        $this->checkout->rememberIntent($request);

        if ($this->checkout->isCustomer($request)) {
            return redirect()->route(CheckoutFlowService::CHECKOUT_ROUTE);
        }

        return view('storefront.pages.customer-login', [
            'checkoutMode' => true,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function address(Request $request): RedirectResponse
    {
        return redirect()->route(CheckoutFlowService::CHECKOUT_ROUTE);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $customer = $this->customerContext->user($request);
        abort_unless($customer !== null, 403);

        if (! $this->checkout->hasCartItems($request)) {
            return redirect()
                ->route('storefront.cart')
                ->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'address_id' => ['required', 'integer', 'exists:merchant_customer_addresses,id'],
            'billing_same_as_delivery' => ['nullable', 'boolean'],
            'billing_address_id' => ['nullable', 'integer', 'exists:merchant_customer_addresses,id'],
            'shipping_method' => ['required', Rule::in(['standard'])],
            'payment_method' => ['required', Rule::in(['cod'])],
            'browser_total' => ['nullable'],
        ]);

        $address = \App\Models\MerchantCustomerAddress::query()->findOrFail($data['address_id']);
        abort_unless($this->checkoutPage->addressBelongsToCustomer($address, $customer), 404);

        $request->session()->put(CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY, $address->getKey());
        $billingSameAsDelivery = (bool) ($data['billing_same_as_delivery'] ?? $this->checkoutPage->billingSameAsDelivery($request));

        if ($billingSameAsDelivery) {
            $billingAddress = $address;
            $request->session()->put(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, true);
            $request->session()->forget(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY);
        } else {
            $billingAddressId = (int) ($data['billing_address_id'] ?? 0);

            if ($billingAddressId <= 0) {
                throw ValidationException::withMessages([
                    'billing_address_id' => 'Please select a billing address.',
                ]);
            }

            $billingAddress = \App\Models\MerchantCustomerAddress::query()->findOrFail($billingAddressId);
            abort_unless($this->checkoutPage->addressBelongsToCustomer($billingAddress, $customer), 404);
            $request->session()->put(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, false);
            $request->session()->put(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY, $billingAddress->getKey());
        }

        return redirect()
            ->route('storefront.checkout')
            ->with('info', 'Order placement will be enabled in the next checkout step.');
    }
}
