<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Cart\CartPageService;
use App\Services\Checkout\CheckoutPageService;
use App\Services\Checkout\CheckoutPostalCodeLookupService;
use App\Services\Customer\CustomerAddressService;
use App\Services\Customer\StorefrontCustomerAddressValidator;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutAddressController extends Controller
{
    public function __construct(
        private readonly CartPageService $cartPage,
        private readonly CheckoutPageService $checkoutPage,
        private readonly CheckoutPostalCodeLookupService $postalLookup,
        private readonly CustomerAddressService $addresses,
        private readonly StorefrontCustomerAddressValidator $addressValidator,
        private readonly StorefrontCustomerContext $customerContext,
    ) {
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $customer = $this->globalCustomerOrAbort($request);
        $data = $this->validatedAddress($request);
        $address = $this->addresses->create($customer, $data);

        $request->session()->put(CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY, $address->getKey());

        return $this->checkoutResponse($request, 'Delivery address added.');
    }

    public function storeBilling(Request $request): JsonResponse|RedirectResponse
    {
        $customer = $this->globalCustomerOrAbort($request);
        $data = $this->validatedAddress($request);
        $data['is_default_shipping'] = (bool) ($data['is_default_shipping'] ?? false);
        $address = $this->addresses->create($customer, $data);

        $request->session()->put(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, false);
        $request->session()->put(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY, $address->getKey());

        return $this->checkoutResponse($request, 'Billing address added.');
    }

    public function update(Request $request, CustomerAddress $address): JsonResponse|RedirectResponse
    {
        $globalCustomer = $this->globalCustomerOrAbort($request);
        abort_unless($this->checkoutPage->addressBelongsToCustomer($address, $globalCustomer), 404);

        $address = $this->addresses->update($address, $this->validatedAddress($request));
        $request->session()->put(CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY, $address->getKey());

        return $this->checkoutResponse($request, 'Delivery address updated.');
    }

    public function select(Request $request): RedirectResponse
    {
        $customer = $this->globalCustomerOrAbort($request);
        $data = $request->validate([
            'address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
        ]);
        $address = CustomerAddress::query()->findOrFail($data['address_id']);

        abort_unless($this->checkoutPage->addressBelongsToCustomer($address, $customer), 404);
        $request->session()->put(CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY, $address->getKey());

        return redirect()
            ->route('storefront.checkout')
            ->with('success', 'Delivery address selected.');
    }

    public function billingSame(Request $request): JsonResponse|RedirectResponse
    {
        $this->customerOrAbort($request);
        $data = $request->validate([
            'billing_same_as_delivery' => ['required', 'boolean'],
        ]);

        $same = (bool) $data['billing_same_as_delivery'];
        $request->session()->put(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, $same);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'billing_same_as_delivery' => $same,
            ]);
        }

        return redirect()->route('storefront.checkout');
    }

    public function selectBilling(Request $request): RedirectResponse
    {
        $customer = $this->globalCustomerOrAbort($request);
        $data = $request->validate([
            'billing_address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
        ]);
        $address = CustomerAddress::query()->findOrFail($data['billing_address_id']);

        abort_unless($this->checkoutPage->addressBelongsToCustomer($address, $customer), 404);
        $request->session()->put(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, false);
        $request->session()->put(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY, $address->getKey());

        return redirect()
            ->route('storefront.checkout')
            ->with('success', 'Billing address selected.');
    }

    public function postalCode(Request $request, string $postalCode): JsonResponse
    {
        $this->customerOrAbort($request);

        return response()->json(
            $this->postalLookup->lookupDefaultPostalCode($postalCode, $this->cartPage->currentCart($request))
        );
    }

    private function customerOrAbort(Request $request): User
    {
        $customer = $this->customerContext->user($request);
        abort_unless($customer instanceof User, 403);

        return $customer;
    }

    private function globalCustomerOrAbort(Request $request): Customer
    {
        $customer = $this->customerContext->customer($request);
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAddress(Request $request): array
    {
        return $this->addressValidator->validate($request);
    }

    private function checkoutResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'checkout_url' => route('storefront.checkout'),
            ]);
        }

        return redirect()
            ->route('storefront.checkout')
            ->with('success', $message);
    }
}
