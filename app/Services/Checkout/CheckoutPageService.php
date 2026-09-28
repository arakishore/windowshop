<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Cart\CartPageService;
use App\Services\Storefront\CustomerLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
        private readonly StorefrontPaymentMethodService $payments,
        private readonly CheckoutFlowService $checkout,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pageData(Request $request, User $customer): array
    {
        $cart = $this->cartPage->currentCart($request);
        $cartData = $this->checkout->checkoutData($request);

        if ($cartData === null) {
            throw ValidationException::withMessages([
                'cart' => 'Please choose a shop from your cart to continue checkout.',
            ]);
        }
        $addresses = $this->addressesFor($customer);
        $selectedAddress = $this->selectedAddress($request, $addresses);
        $billingSameAsDelivery = $this->billingSameAsDelivery($request);
        $selectedBillingAddress = $this->selectedBillingAddress($request, $addresses, $selectedAddress);
        $selectedPostalCode = $selectedAddress instanceof CustomerAddress
            ? $this->location->normalize($selectedAddress->postal_code)
            : null;
        $deliveryData = $this->delivery->resolve($request, $cart, $cartData, $selectedAddress);
        $cartData['shipping_cents'] = $deliveryData['shipping_cents'];
        $cartData['shipping'] = $deliveryData['shipping'];
        $cartData['total_cents'] = $deliveryData['total_cents'];
        $cartData['total'] = $deliveryData['total'];
        $paymentData = $this->payments->resolve($request, $cart, $cartData, $deliveryData['selected']);
        $hasFulfillmentAddress = $deliveryData['selected'] !== StorefrontDeliveryService::FULFILLMENT_DELIVERY
            || $selectedAddress instanceof CustomerAddress;
        $hasFulfillmentPostalCode = $deliveryData['selected'] !== StorefrontDeliveryService::FULFILLMENT_DELIVERY
            || $selectedPostalCode !== null;

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
            'primaryMerchantId' => $this->primaryMerchantId($cart, $this->checkout->selectedShopId($request)),
            'countries' => $this->postalLookup->countries(),
            'defaultCountry' => $this->postalLookup->defaultCountry(),
            'shippingOptions' => $deliveryData['options'],
            'selectedFulfillment' => $deliveryData['selected'],
            'shippingTotal' => $deliveryData['shipping'],
            'paymentMethods' => $paymentData['methods'],
            'selectedPaymentMethod' => $paymentData['selected'],
            'paymentUnavailableMessage' => $paymentData['message'],
            'canPlaceOrder' => $hasFulfillmentAddress
                && $selectedBillingAddress instanceof CustomerAddress
                && $hasFulfillmentPostalCode
                && $deliveryData['selected'] !== null
                && $paymentData['selected'] !== null
                && ! (bool) $cartData['is_empty'],
        ];
    }

    public function billingSameAsDelivery(Request $request): bool
    {
        return (bool) $request->session()->get(self::BILLING_SAME_AS_DELIVERY_SESSION_KEY, true);
    }

    public function addressBelongsToCustomer(CustomerAddress $address, Customer $customer): bool
    {
        return (int) $address->customer_id === (int) $customer->getKey()
            && $address->status === CustomerAddress::STATUS_ACTIVE;
    }

    /**
     * @return Collection<int, CustomerAddress>
     */
    public function addressesFor(User|Customer $customer): Collection
    {
        $customerId = $customer instanceof Customer
            ? $customer->getKey()
            : $customer->customer?->getKey();

        if ($customerId === null) {
            return collect();
        }

        return CustomerAddress::query()
            ->with(['country', 'state', 'city'])
            ->where('customer_id', $customerId)
            ->where('status', CustomerAddress::STATUS_ACTIVE)
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('id')
            ->get();
    }

    public function selectedAddress(Request $request, Collection $addresses): ?CustomerAddress
    {
        $selectedId = (int) $request->session()->get(self::SELECTED_ADDRESS_SESSION_KEY, 0);
        $selected = $selectedId > 0
            ? $addresses->first(fn (CustomerAddress $address): bool => (int) $address->getKey() === $selectedId)
            : null;

        if ($selected instanceof CustomerAddress) {
            return $selected;
        }

        $default = $addresses->first(fn (CustomerAddress $address): bool => (bool) $address->is_default_shipping);

        if ($default instanceof CustomerAddress) {
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
        ?CustomerAddress $selectedDeliveryAddress = null,
    ): ?CustomerAddress {
        if ($this->billingSameAsDelivery($request)) {
            return $selectedDeliveryAddress;
        }

        $selectedId = (int) $request->session()->get(self::SELECTED_BILLING_ADDRESS_SESSION_KEY, 0);
        $selected = $selectedId > 0
            ? $addresses->first(fn (CustomerAddress $address): bool => (int) $address->getKey() === $selectedId)
            : null;

        if ($selected instanceof CustomerAddress) {
            return $selected;
        }

        $default = $addresses->first(fn (CustomerAddress $address): bool => (bool) $address->is_default_billing);

        if ($default instanceof CustomerAddress) {
            $request->session()->put(self::SELECTED_BILLING_ADDRESS_SESSION_KEY, $default->getKey());

            return $default;
        }

        $request->session()->forget(self::SELECTED_BILLING_ADDRESS_SESSION_KEY);

        return null;
    }

    private function primaryMerchantId(?Cart $cart, ?int $shopId): ?int
    {
        $item = $cart?->items->first(
            fn ($item): bool => (int) $item->shop_id === $shopId,
        );

        return $item?->shop?->merchant_id !== null ? (int) $item->shop->merchant_id : null;
    }

}
