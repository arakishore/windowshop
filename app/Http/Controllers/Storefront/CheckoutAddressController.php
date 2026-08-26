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
use App\Services\Storefront\StorefrontCustomerContext;
use App\Services\Storefront\StorefrontCountryResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutAddressController extends Controller
{
    public function __construct(
        private readonly CartPageService $cartPage,
        private readonly CheckoutPageService $checkoutPage,
        private readonly CheckoutPostalCodeLookupService $postalLookup,
        private readonly CustomerAddressService $addresses,
        private readonly StorefrontCustomerContext $customerContext,
        private readonly StorefrontCountryResolver $countries,
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
        $data = $request->validate([
            'label' => ['required', Rule::in(['Home', 'Work', 'Other'])],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_mobile_country_code' => ['nullable', 'string', 'max:10'],
            'recipient_mobile' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:190'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'state_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in([CustomerAddress::STATUS_ACTIVE])],
        ]) + [
            'status' => CustomerAddress::STATUS_ACTIVE,
        ];
        $country = $this->countries->defaultCountry();
        $data['country_id'] = $country->getKey();

        if ($this->countries->isIndia($country)) {
            $lookup = $this->postalLookup->lookupIndiaPin((string) ($data['postal_code'] ?? ''));

            if (! $lookup['valid']) {
                throw ValidationException::withMessages([
                    'postal_code' => 'Please enter a valid Indian PIN code.',
                ]);
            }

            $location = $this->postalLookup->resolveIndiaAddressLocation((string) $lookup['postal_code']);
            $data['country_id'] = $location['country_id'];
            $data['state_id'] = $location['state_id'];
            $data['city_id'] = $location['city_id'];
            $data['postal_code'] = $lookup['postal_code'];

            return $data;
        }

        if (($data['state_id'] ?? null) !== null) {
            $stateExists = DB::table('loc_states')
                ->where('id', (int) $data['state_id'])
                ->where('country_id', (int) $data['country_id'])
                ->where('status', true)
                ->whereNull('deleted_at')
                ->exists();

            if (! $stateExists) {
                throw ValidationException::withMessages([
                    'state_id' => 'Please select a valid state for the selected country.',
                ]);
            }
        }

        if (($data['city_id'] ?? null) !== null) {
            $cityExists = DB::table('loc_cities')
                ->where('id', (int) $data['city_id'])
                ->where('country_id', (int) $data['country_id'])
                ->when(($data['state_id'] ?? null) !== null, fn ($query) => $query->where('state_id', (int) $data['state_id']))
                ->whereNull('deleted_at')
                ->exists();

            if (! $cityExists) {
                throw ValidationException::withMessages([
                    'city_id' => 'Please select a valid city for the selected state.',
                ]);
            }
        }

        return $data;
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
