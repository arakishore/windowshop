<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\MerchantCustomerAddress;
use App\Models\User;
use App\Services\Cart\CartPageService;
use App\Services\Storefront\CustomerLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CheckoutPageService
{
    public const SELECTED_ADDRESS_SESSION_KEY = 'storefront.checkout.selected_address_id';
    public const BILLING_SAME_AS_DELIVERY_SESSION_KEY = 'storefront.checkout.billing_same_as_delivery';
    public const SELECTED_BILLING_ADDRESS_SESSION_KEY = 'storefront.checkout.selected_billing_address_id';

    public function __construct(
        private readonly CartPageService $cartPage,
        private readonly CheckoutPostalCodeLookupService $postalLookup,
        private readonly CustomerLocationService $location,
        private readonly StorefrontDeliveryService $delivery,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pageData(Request $request, User $customer): array
    {
        $cart = $this->cartPage->currentCart($request);
        $cartData = $this->cartPage->pageData($request);
        $addresses = $this->addressesFor($customer);
        $selectedAddress = $this->selectedAddress($request, $addresses);
        $billingSameAsDelivery = $this->billingSameAsDelivery($request);
        $selectedBillingAddress = $this->selectedBillingAddress($request, $addresses, $selectedAddress);
        $selectedPostalCode = $selectedAddress instanceof MerchantCustomerAddress
            ? $this->location->normalize($selectedAddress->postal_code)
            : null;
        $deliveryData = $this->delivery->resolve($request, $cart, $cartData, $selectedAddress);
        $cartData['shipping_cents'] = $deliveryData['shipping_cents'];
        $cartData['shipping'] = $deliveryData['shipping'];
        $cartData['total_cents'] = $deliveryData['total_cents'];
        $cartData['total'] = $deliveryData['total'];

        return [
            'cart' => $cart,
            'cartData' => $cartData,
            'addresses' => $addresses,
            'selectedAddress' => $selectedAddress,
            'selectedAddressId' => $selectedAddress?->getKey(),
            'billingSameAsDelivery' => $billingSameAsDelivery,
            'selectedBillingAddress' => $selectedBillingAddress,
            'selectedBillingAddressId' => $selectedBillingAddress?->getKey(),
            'selectedPostalCode' => $selectedPostalCode,
            'primaryMerchantId' => $this->primaryMerchantId($cart),
            'countries' => $this->postalLookup->countries(),
            'defaultCountry' => $this->postalLookup->defaultCountry(),
            'shippingOptions' => $deliveryData['options'],
            'selectedFulfillment' => $deliveryData['selected'],
            'shippingTotal' => $deliveryData['shipping'],
            'paymentMethods' => $this->paymentMethods(),
            'canPlaceOrder' => $selectedAddress instanceof MerchantCustomerAddress
                && $selectedBillingAddress instanceof MerchantCustomerAddress
                && $selectedPostalCode !== null
                && $deliveryData['selected'] !== null
                && ! (bool) $cartData['is_empty'],
        ];
    }

    public function billingSameAsDelivery(Request $request): bool
    {
        return (bool) $request->session()->get(self::BILLING_SAME_AS_DELIVERY_SESSION_KEY, true);
    }

    public function addressBelongsToCustomer(MerchantCustomerAddress $address, User $customer): bool
    {
        $address->loadMissing('customer');

        return (int) $address->customer?->user_id === (int) $customer->getKey()
            && $address->status === MerchantCustomerAddress::STATUS_ACTIVE;
    }

    /**
     * @return Collection<int, MerchantCustomerAddress>
     */
    public function addressesFor(User $customer): Collection
    {
        return MerchantCustomerAddress::query()
            ->with(['customer.merchant', 'country', 'state', 'city'])
            ->where('status', MerchantCustomerAddress::STATUS_ACTIVE)
            ->whereHas('customer', function ($query) use ($customer): void {
                $query->where('user_id', $customer->getKey())
                    ->where('status', 'active');
            })
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('id')
            ->get();
    }

    public function selectedAddress(Request $request, Collection $addresses): ?MerchantCustomerAddress
    {
        $selectedId = (int) $request->session()->get(self::SELECTED_ADDRESS_SESSION_KEY, 0);
        $selected = $selectedId > 0
            ? $addresses->first(fn (MerchantCustomerAddress $address): bool => (int) $address->getKey() === $selectedId)
            : null;

        if ($selected instanceof MerchantCustomerAddress) {
            return $selected;
        }

        $default = $addresses->first(fn (MerchantCustomerAddress $address): bool => (bool) $address->is_default_shipping);

        if ($default instanceof MerchantCustomerAddress) {
            $request->session()->put(self::SELECTED_ADDRESS_SESSION_KEY, $default->getKey());

            return $default;
        }

        if ($addresses->count() === 1) {
            $only = $addresses->first();
            $request->session()->put(self::SELECTED_ADDRESS_SESSION_KEY, $only->getKey());

            return $only;
        }

        $request->session()->forget(self::SELECTED_ADDRESS_SESSION_KEY);

        return null;
    }

    public function selectedBillingAddress(
        Request $request,
        Collection $addresses,
        ?MerchantCustomerAddress $selectedDeliveryAddress = null,
    ): ?MerchantCustomerAddress {
        if ($this->billingSameAsDelivery($request)) {
            return $selectedDeliveryAddress;
        }

        $selectedId = (int) $request->session()->get(self::SELECTED_BILLING_ADDRESS_SESSION_KEY, 0);
        $selected = $selectedId > 0
            ? $addresses->first(fn (MerchantCustomerAddress $address): bool => (int) $address->getKey() === $selectedId)
            : null;

        if ($selected instanceof MerchantCustomerAddress) {
            return $selected;
        }

        $default = $addresses->first(fn (MerchantCustomerAddress $address): bool => (bool) $address->is_default_billing);

        if ($default instanceof MerchantCustomerAddress) {
            $request->session()->put(self::SELECTED_BILLING_ADDRESS_SESSION_KEY, $default->getKey());

            return $default;
        }

        $request->session()->forget(self::SELECTED_BILLING_ADDRESS_SESSION_KEY);

        return null;
    }

    private function primaryMerchantId(?Cart $cart): ?int
    {
        $item = $cart?->items->first();

        return $item?->shop?->merchant_id !== null ? (int) $item->shop->merchant_id : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paymentMethods(): array
    {
        return [
            [
                'id' => 'cod',
                'label' => 'Cash on Delivery',
                'description' => 'Pay when the shop delivers or hands over the order.',
                'enabled' => true,
                'selected' => true,
            ],
        ];
    }
}
