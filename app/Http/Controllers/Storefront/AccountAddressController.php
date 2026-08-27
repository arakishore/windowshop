<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Checkout\CheckoutPageService;
use App\Services\Checkout\CheckoutPostalCodeLookupService;
use App\Services\Customer\CustomerAddressService;
use App\Services\Customer\StorefrontCustomerAddressValidator;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountAddressController extends Controller
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly StorefrontCustomerContext $customerContext,
        private readonly CheckoutPageService $checkoutPage,
        private readonly CheckoutPostalCodeLookupService $postalLookup,
        private readonly CustomerAddressService $addresses,
        private readonly StorefrontCustomerAddressValidator $addressValidator,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.addresses', $this->accountViewData($customer, [
            'addresses' => $this->checkoutPage->addressesFor($customer),
        ]));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.address-form', $this->accountViewData($customer, $this->formData(null, [
            'title' => 'Add Address',
            'action' => route('storefront.account.addresses.store'),
            'method' => 'POST',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $this->globalCustomerOrAbort($request);
        $this->addresses->create($customer, $this->addressValidator->validate($request));

        return redirect()
            ->route('storefront.account.addresses')
            ->with('success', 'Address added.');
    }

    public function edit(Request $request, CustomerAddress $address): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        $this->addressForCustomer($address, $this->globalCustomerOrAbort($request));

        return view('storefront.account.address-form', $this->accountViewData($customer, $this->formData($address, [
            'title' => 'Edit Address',
            'action' => route('storefront.account.addresses.update', $address),
            'method' => 'PUT',
        ])));
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->addressForCustomer($address, $this->globalCustomerOrAbort($request));
        $this->addresses->update($address, $this->addressValidator->validate($request));

        return redirect()
            ->route('storefront.account.addresses')
            ->with('success', 'Address updated.');
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->addressForCustomer($address, $this->globalCustomerOrAbort($request));
        $this->addresses->delete($address);

        return redirect()
            ->route('storefront.account.addresses')
            ->with('success', 'Address deleted.');
    }

    public function defaultDelivery(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->addressForCustomer($address, $this->globalCustomerOrAbort($request));
        $this->addresses->setDefaultShipping($address);

        return redirect()
            ->route('storefront.account.addresses')
            ->with('success', 'Default delivery address updated.');
    }

    public function defaultBilling(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->addressForCustomer($address, $this->globalCustomerOrAbort($request));
        $this->addresses->setDefaultBilling($address);

        return redirect()
            ->route('storefront.account.addresses')
            ->with('success', 'Default billing address updated.');
    }

    public function states(Request $request): JsonResponse
    {
        $this->globalCustomerOrAbort($request);

        $countryId = (int) $request->query('country_id', 0);

        return response()->json([
            'states' => DB::table('loc_states')
                ->select(['id', 'name'])
                ->where('country_id', $countryId)
                ->where('status', true)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $this->globalCustomerOrAbort($request);

        $countryId = (int) $request->query('country_id', 0);
        $stateId = (int) $request->query('state_id', 0);

        return response()->json([
            'cities' => DB::table('loc_cities')
                ->select(['id', 'name'])
                ->where('country_id', $countryId)
                ->when($stateId > 0, fn ($query) => $query->where('state_id', $stateId))
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function customerOrRedirect(Request $request): User|RedirectResponse
    {
        $customer = $this->customerContext->user($request);

        return $customer instanceof User
            ? $customer
            : redirect()->route('storefront.login');
    }

    private function globalCustomerOrAbort(Request $request): Customer
    {
        $customer = $this->customerContext->customer($request);
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    private function addressForCustomer(CustomerAddress $address, Customer $customer): CustomerAddress
    {
        abort_unless(
            (int) $address->customer_id === (int) $customer->getKey()
                && $address->status === CustomerAddress::STATUS_ACTIVE,
            404,
        );

        return $address;
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

    /**
     * @param array<string, string> $meta
     * @return array<string, mixed>
     */
    private function formData(?CustomerAddress $address, array $meta): array
    {
        $defaultCountry = $this->postalLookup->defaultCountry();
        $selectedCountryId = (int) old('country_id', $address?->country_id ?? $defaultCountry->getKey());
        $selectedStateId = (int) old('state_id', $address?->state_id ?? 0);

        return $meta + [
            'address' => $address,
            'countries' => $this->postalLookup->countries(),
            'states' => $this->statesFor($selectedCountryId),
            'cities' => $this->citiesFor($selectedCountryId, $selectedStateId),
            'defaultCountry' => $defaultCountry,
            'selectedCountryId' => $selectedCountryId,
            'selectedStateId' => $selectedStateId,
            'selectedCityId' => (int) old('city_id', $address?->city_id ?? 0),
            'selectedStateName' => (string) old('state_name', $address?->state?->name ?? ''),
            'selectedCityName' => (string) old('city_name', $address?->city?->name ?? ''),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function statesFor(int $countryId): Collection
    {
        return DB::table('loc_states')
            ->select(['id', 'name'])
            ->where('country_id', $countryId)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function citiesFor(int $countryId, int $stateId): Collection
    {
        return DB::table('loc_cities')
            ->select(['id', 'name'])
            ->where('country_id', $countryId)
            ->when($stateId > 0, fn ($query) => $query->where('state_id', $stateId))
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }
}
