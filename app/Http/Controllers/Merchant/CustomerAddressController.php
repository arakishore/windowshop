<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UpsertMerchantCustomerAddressRequest;
use App\Models\MerchantCustomer;
use App\Models\MerchantCustomerAddress;
use App\Models\MerchantProfile;
use App\Services\Merchant\MerchantCustomerAddressService;
use App\Services\Merchant\MerchantService;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly MerchantCustomerAddressService $addressService,
        private readonly MerchantService $merchantService,
    ) {
    }

    public function create(Request $request, MerchantCustomer $customer): View
    {
        $this->authorizeCustomer($request, $customer);
        $address = new MerchantCustomerAddress([
            'recipient_name' => $customer->name,
            'recipient_mobile_country_code' => $customer->mobile_country_code,
            'recipient_mobile' => $customer->mobile,
            'status' => MerchantCustomerAddress::STATUS_ACTIVE,
        ]);

        return view('merchant.customers.addresses.create', [
            'customer' => $customer,
            'address' => $address,
            ...$this->formData($request, $address),
        ]);
    }

    public function store(UpsertMerchantCustomerAddressRequest $request, MerchantCustomer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);
        $this->addressService->create($customer, $request->validated());

        return redirect()
            ->route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses'])
            ->with('success', 'Customer address created successfully.');
    }

    public function edit(Request $request, MerchantCustomer $customer, MerchantCustomerAddress $address): View
    {
        $this->authorizeAddress($request, $customer, $address);

        return view('merchant.customers.addresses.edit', [
            'customer' => $customer,
            'address' => $address,
            ...$this->formData($request, $address),
        ]);
    }

    public function update(UpsertMerchantCustomerAddressRequest $request, MerchantCustomer $customer, MerchantCustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $customer, $address);
        $this->addressService->update($address, $request->validated());

        return redirect()
            ->route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses'])
            ->with('success', 'Customer address updated successfully.');
    }

    public function destroy(Request $request, MerchantCustomer $customer, MerchantCustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $customer, $address);
        $address->delete();

        return redirect()
            ->route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses'])
            ->with('success', 'Customer address deleted successfully.');
    }

    public function states(Request $request): JsonResponse
    {
        $countryId = (int) $request->query('country_id');

        return response()->json($countryId ? $this->merchantService->activeStates($countryId) : []);
    }

    public function cities(Request $request): JsonResponse
    {
        $countryId = (int) $request->query('country_id');
        $stateId = (int) $request->query('state_id');

        return response()->json($countryId && $stateId ? $this->merchantService->citiesForState($countryId, $stateId) : []);
    }

    private function authorizeCustomer(Request $request, MerchantCustomer $customer): MerchantProfile
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);
        abort_unless((int) $customer->merchant_id === (int) $merchant->getKey(), 404);

        return $merchant;
    }

    private function authorizeAddress(Request $request, MerchantCustomer $customer, MerchantCustomerAddress $address): MerchantProfile
    {
        $merchant = $this->authorizeCustomer($request, $customer);
        abort_unless((int) $address->merchant_customer_id === (int) $customer->getKey(), 404);

        return $merchant;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, MerchantCustomerAddress $address): array
    {
        $defaultLocation = $this->merchantService->defaultBusinessLocation();
        $countryId = (int) old('country_id', $address->country_id ?? $defaultLocation['country_id']);
        $stateId = (int) old('state_id', $address->state_id ?? ($countryId === (int) $defaultLocation['country_id'] ? $defaultLocation['state_id'] : null));

        return [
            'countries' => $this->merchantService->activeCountries(),
            'states' => $countryId ? $this->merchantService->activeStates($countryId) : collect(),
            'cities' => $countryId && $stateId ? $this->merchantService->citiesForState($countryId, $stateId) : collect(),
            'selectedCountryId' => $countryId,
            'selectedStateId' => $stateId,
            'selectedCityId' => (int) old('city_id', $address->city_id ?? ($stateId === (int) $defaultLocation['state_id'] ? $defaultLocation['city_id'] : null)),
            'statuses' => $this->statuses(),
            'statesUrl' => route('merchant.customer-addresses.states'),
            'citiesUrl' => route('merchant.customer-addresses.cities'),
        ];
    }

    /**
     * @return array<string, array{label: string, badge_class: string}>
     */
    private function statuses(): array
    {
        return config('admin.customer_address.statuses', [
            MerchantCustomerAddress::STATUS_ACTIVE => ['label' => 'Active', 'badge_class' => 'bg-success'],
            MerchantCustomerAddress::STATUS_INACTIVE => ['label' => 'Inactive', 'badge_class' => 'bg-light text-body border'],
        ]);
    }
}
