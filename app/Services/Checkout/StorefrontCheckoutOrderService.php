<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\MerchantCustomer;
use App\Models\Order;
use App\Models\OrderTotal;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\AdminSettingsService;
use App\Services\Cart\CartPageService;
use App\Services\Merchant\MerchantCustomerService;
use App\Services\Order\OrderCreationService;
use App\Services\Promotion\Coupons\CouponSessionStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StorefrontCheckoutOrderService
{
    public function __construct(
        private readonly CartPageService $cartPage,
        private readonly CheckoutPageService $checkoutPage,
        private readonly StorefrontDeliveryService $delivery,
        private readonly StorefrontPaymentMethodService $payments,
        private readonly OrderCreationService $orders,
        private readonly MerchantCustomerService $merchantCustomers,
        private readonly AdminSettingsService $adminSettings,
        private readonly CouponSessionStore $couponStore,
    ) {
    }

    public function place(
        Request $request,
        User $actor,
        Customer $customer,
        string $fulfillment,
        string $paymentMethod,
        CustomerAddress $billingAddress,
        ?string $customerOrderNote = null,
    ): Order
    {
        return DB::transaction(function () use ($request, $actor, $customer, $fulfillment, $paymentMethod, $billingAddress, $customerOrderNote): Order {
            $cart = $this->lockedCart($request);
            $cartData = $this->cartPage->pageData($request);

            if ((bool) ($cartData['is_empty'] ?? true)) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $groups = collect($cartData['shop_groups'] ?? []);
            $unavailable = $groups
                ->flatMap(fn (array $group): array => $group['items'] ?? [])
                ->first(fn (array $item): bool => ! (bool) ($item['is_available'] ?? false));

            if (is_array($unavailable)) {
                throw ValidationException::withMessages([
                    'items' => $unavailable['availability_message'] ?? 'Please review unavailable cart items.',
                ]);
            }

            if ($groups->count() !== 1) {
                throw ValidationException::withMessages([
                    'cart' => 'Please place orders from one shop at a time.',
                ]);
            }

            $shippingAddress = $this->selectedShippingAddress($request, $customer, $fulfillment);
            $deliveryData = $this->delivery->resolve($request, $cart, $cartData, $shippingAddress);

            if ($deliveryData['selected'] !== $fulfillment) {
                throw ValidationException::withMessages([
                    'shipping_method' => 'Please select a valid delivery or pickup option.',
                ]);
            }

            $cartData['shipping_cents'] = $deliveryData['shipping_cents'];
            $cartData['shipping'] = $deliveryData['shipping'];
            $cartData['total_cents'] = $deliveryData['total_cents'];
            $cartData['total'] = $deliveryData['total'];

            if (! $this->payments->isAvailable($request, $cart, $cartData, $fulfillment, $paymentMethod)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Please select a valid payment method for this delivery option.',
                ]);
            }

            $group = $groups->first();
            $shop = $cart->items
                ->map(fn ($item): ?Shop => $item->shop)
                ->filter()
                ->first(fn (Shop $shop): bool => (int) $shop->getKey() === (int) ($group['shop_id'] ?? 0));

            if (! $shop instanceof Shop) {
                throw ValidationException::withMessages([
                    'cart' => 'The selected shop is no longer available.',
                ]);
            }

            $merchantCustomer = $this->merchantCustomer($shop, $customer);
            $order = $this->orders->create([
                'shop_id' => $shop->getKey(),
                'customer_id' => $customer->getKey(),
                'shipping_address_snapshot' => $fulfillment === StorefrontDeliveryService::FULFILLMENT_DELIVERY && $shippingAddress instanceof CustomerAddress
                    ? $this->addressSnapshot($shippingAddress)
                    : null,
                'billing_address_snapshot' => $this->addressSnapshot($billingAddress),
                'created_source' => Order::SOURCE_STOREFRONT,
                'fulfilment_type' => $fulfillment,
                'order_status' => Order::STATUS_PENDING,
                'payment_method' => $paymentMethod,
                'payment_status' => Order::PAYMENT_PENDING,
                'currency_code' => $this->adminSettings->currencyConfig()['currency'] ?? 'INR',
                'cash_rounding' => ['method' => 'none', 'applyTo' => []],
                'amount_paid' => 0,
                'customer_order_note' => $this->nullableString($customerOrderNote),
                'status_note' => 'Storefront order placed',
                'applied_coupon_code' => $this->couponStore->get($request, (int) $shop->getKey()),
                'items' => $this->orderItems($group['items'] ?? []),
                'totals' => $this->totalsRows((int) $deliveryData['shipping_cents'], $deliveryData),
            ], $actor);

            $cart->items()->delete();
            $this->couponStore->forget($request, (int) $shop->getKey());
            $this->clearCheckoutState($request);

            return $order;
        });
    }

    private function lockedCart(Request $request): Cart
    {
        $cart = $this->cartPage->currentCart($request);
        if (! $cart instanceof Cart) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $locked = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->first();
        if (! $locked instanceof Cart) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $locked->items()->lockForUpdate()->get();
        $locked->load([
            'items' => fn ($query) => $query->orderBy('shop_id')->orderBy('id'),
            'items.shop.city',
            'items.shop.merchant',
            'items.product',
            'items.productVariant',
        ]);

        return $locked;
    }

    private function selectedShippingAddress(Request $request, Customer $customer, string $fulfillment): ?CustomerAddress
    {
        if ($fulfillment !== StorefrontDeliveryService::FULFILLMENT_DELIVERY) {
            return null;
        }

        $addresses = $this->checkoutPage->addressesFor($customer);
        $address = $this->checkoutPage->selectedAddress($request, $addresses);

        if (! $address instanceof CustomerAddress) {
            throw ValidationException::withMessages([
                'address_id' => 'Please select a delivery address.',
            ]);
        }

        return $address;
    }

    private function merchantCustomer(Shop $shop, Customer $customer): MerchantCustomer
    {
        $existing = MerchantCustomer::query()
            ->where('merchant_id', $shop->merchant_id)
            ->where('customer_id', $customer->getKey())
            ->where('status', MerchantCustomer::STATUS_ACTIVE)
            ->first();

        if ($existing instanceof MerchantCustomer) {
            return $existing;
        }

        return $this->merchantCustomers->create($shop->merchant, [
            'name' => $customer->name,
            'mobile_country_code' => $customer->mobile_country_code,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
            'linked_at' => now(),
            'status' => MerchantCustomer::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function addressSnapshot(CustomerAddress $address): array
    {
        $address->loadMissing(['city', 'state', 'country']);

        return [
            'recipient_name' => $address->recipient_name,
            'mobile_country_code' => $address->recipient_mobile_country_code,
            'mobile' => $address->recipient_mobile,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'landmark' => $address->landmark,
            'city' => $address->city?->name,
            'state' => $address->state?->name,
            'country' => $address->country?->name,
            'postal_code' => $address->postal_code,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array{product_variant_id: int, quantity: int}>
     */
    private function orderItems(array $items): array
    {
        $rows = collect($items)
            ->reject(fn (array $item): bool => (bool) ($item['is_generated_gift'] ?? false))
            ->map(fn (array $item): array => [
                'product_variant_id' => (int) ($item['product_variant_id'] ?? $item['id'] ?? 0),
                'quantity' => (int) ($item['quantity_value'] ?? $item['quantity'] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['product_variant_id'] > 0 && $row['quantity'] > 0)
            ->values()
            ->all();

        if ($rows === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one order item is required.',
            ]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $deliveryData
     * @return array<int, array<string, mixed>>
     */
    private function totalsRows(int $shippingCents, array $deliveryData): array
    {
        if ($shippingCents <= 0) {
            return [];
        }

        return [[
            'code' => OrderTotal::CODE_SHIPPING,
            'title' => 'Shipping',
            'amount' => $shippingCents / 100,
            'sort_order' => 40,
            'source' => 'storefront',
            'metadata' => [
                'fulfillment' => $deliveryData['selected'],
            ],
        ]];
    }

    private function clearCheckoutState(Request $request): void
    {
        $request->session()->forget([
            CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY,
            CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY,
            CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY,
            StorefrontDeliveryService::SELECTED_FULFILLMENT_SESSION_KEY,
            StorefrontPaymentMethodService::SELECTED_PAYMENT_SESSION_KEY,
        ]);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
