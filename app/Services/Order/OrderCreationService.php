<?php

namespace App\Services\Order;

use App\Models\MerchantCustomer;
use App\Models\MerchantCustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTotal;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\POS\DiscountService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreationService
{
    public function __construct(
        private readonly OrderNumberService $orderNumberService,
        private readonly OrderTotalsService $orderTotalsService,
        private readonly OrderStatusService $orderStatusService,
        private readonly DiscountService $discountService,
    ) {
    }

    /**
     * @param array{
     *     shop_id: int,
     *     customer_id?: int|null,
     *     shipping_address_id?: int|null,
     *     created_source?: string,
     *     fulfilment_type?: string,
     *     order_status?: string,
     *     payment_method?: string,
     *     payment_reference?: string|null,
     *     upi_txn?: string|null,
     *     terminal_id?: string|null,
     *     payment_status?: string,
     *     order_discount?: array{type?: string|null, value?: mixed, reason?: string|null, note?: string|null},
     *     amount_paid?: float|string|int,
     *     elapsed_seconds?: int,
     *     customer_name?: string|null,
     *     customer_mobile?: string|null,
     *     customer_email?: string|null,
     *     remarks?: string|null,
     *     items: array<int, array{product_variant_id: int, quantity: int}>,
     *     totals?: array<int, array<string, mixed>>
     * } $data
     */
    public function create(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $shop = Shop::query()->findOrFail((int) $data['shop_id']);
            $rows = $this->aggregateItems($data['items'] ?? []);
            $variants = $this->lockVariants($shop, $rows);
            $items = $this->buildItems($rows, $variants);
            $orderStatus = (string) ($data['order_status'] ?? Order::STATUS_COMPLETED);
            $paymentStatus = (string) ($data['payment_status'] ?? Order::PAYMENT_PAID);
            $customerSnapshot = $this->customerSnapshot($shop, $data);
            $shippingSnapshot = $this->shippingSnapshot($shop, $data, $customerSnapshot['customer_id']);
            $orderDiscount = $this->orderDiscount($items, $data['order_discount'] ?? []);

            $order = Order::query()->create([
                'order_number' => $this->orderNumberService->generate(),
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
                'customer_id' => $customerSnapshot['customer_id'],
                'shipping_address_id' => $shippingSnapshot['shipping_address_id'],
                'created_source' => $data['created_source'] ?? Order::SOURCE_POS,
                'fulfilment_type' => $data['fulfilment_type'] ?? Order::FULFILMENT_COUNTER,
                'order_status' => $orderStatus,
                'payment_method' => $data['payment_method'] ?? Order::PAYMENT_METHOD_CASH,
                'payment_reference' => $data['payment_reference'] ?? null,
                'upi_txn' => $data['upi_txn'] ?? null,
                'terminal_id' => $data['terminal_id'] ?? null,
                'payment_status' => $paymentStatus,
                'currency_code' => $data['currency_code'] ?? 'INR',
                'elapsed_seconds' => max(0, (int) ($data['elapsed_seconds'] ?? 0)),
                'customer_name' => $customerSnapshot['customer_name'],
                'customer_mobile' => $customerSnapshot['customer_mobile'],
                'customer_email' => $customerSnapshot['customer_email'],
                'shipping_recipient_name' => $shippingSnapshot['shipping_recipient_name'],
                'shipping_mobile_country_code' => $shippingSnapshot['shipping_mobile_country_code'],
                'shipping_mobile' => $shippingSnapshot['shipping_mobile'],
                'shipping_address_line_1' => $shippingSnapshot['shipping_address_line_1'],
                'shipping_address_line_2' => $shippingSnapshot['shipping_address_line_2'],
                'shipping_landmark' => $shippingSnapshot['shipping_landmark'],
                'shipping_city' => $shippingSnapshot['shipping_city'],
                'shipping_state' => $shippingSnapshot['shipping_state'],
                'shipping_country' => $shippingSnapshot['shipping_country'],
                'shipping_postal_code' => $shippingSnapshot['shipping_postal_code'],
                'order_discount_type' => $orderDiscount['type'],
                'order_discount_value' => $orderDiscount['value'],
                'order_discount_amount' => $orderDiscount['amount'],
                'order_discount_reason' => $orderDiscount['reason'],
                'order_discount_note' => $orderDiscount['note'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
                'completed_at' => $orderStatus === Order::STATUS_COMPLETED ? now() : null,
                'cancelled_at' => $orderStatus === Order::STATUS_CANCELLED ? now() : null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item->getAttributes());
            }

            $createdItems = $order->items()->get();
            $calculated = $this->orderTotalsService->calculate(
                $createdItems,
                $this->totalsRows($createdItems, $orderDiscount, $data['totals'] ?? []),
                $data['amount_paid'] ?? 0,
            );

            if ($paymentStatus === Order::PAYMENT_PAID && (float) $calculated['summary']['amount_paid'] < (float) $calculated['summary']['grand_total']) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'The amount paid must be at least the grand total for a paid cash sale.',
                ]);
            }

            $this->orderTotalsService->save($order, $calculated['summary'], $calculated['rows']);
            $this->orderStatusService->recordInitial($order, $orderStatus, $actor, 'POS cash sale completed');
            $this->deductStock($variants, $rows);

            return $order->load(['items', 'totals', 'statusHistories']);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array{customer_id: int|null, customer_name: string|null, customer_mobile: string|null, customer_email: string|null}
     */
    private function customerSnapshot(Shop $shop, array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);

        if ($customerId < 1) {
            return [
                'customer_id' => null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_mobile' => $data['customer_mobile'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
            ];
        }

        $customer = MerchantCustomer::query()
            ->whereKey($customerId)
            ->where('merchant_id', $shop->merchant_id)
            ->firstOrFail();

        return [
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     shipping_address_id: int|null,
     *     shipping_recipient_name: string|null,
     *     shipping_mobile_country_code: string|null,
     *     shipping_mobile: string|null,
     *     shipping_address_line_1: string|null,
     *     shipping_address_line_2: string|null,
     *     shipping_landmark: string|null,
     *     shipping_city: string|null,
     *     shipping_state: string|null,
     *     shipping_country: string|null,
     *     shipping_postal_code: string|null
     * }
     */
    private function shippingSnapshot(Shop $shop, array $data, ?int $customerId): array
    {
        $empty = [
            'shipping_address_id' => null,
            'shipping_recipient_name' => null,
            'shipping_mobile_country_code' => null,
            'shipping_mobile' => null,
            'shipping_address_line_1' => null,
            'shipping_address_line_2' => null,
            'shipping_landmark' => null,
            'shipping_city' => null,
            'shipping_state' => null,
            'shipping_country' => null,
            'shipping_postal_code' => null,
        ];

        if (($data['fulfilment_type'] ?? Order::FULFILMENT_COUNTER) !== Order::FULFILMENT_DELIVERY) {
            return $empty;
        }

        if ($customerId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Please select a customer for delivery orders.',
            ]);
        }

        $addressId = (int) ($data['shipping_address_id'] ?? 0);
        if ($addressId < 1) {
            throw ValidationException::withMessages([
                'shipping_address_id' => 'Please choose a shipping address for delivery orders.',
            ]);
        }

        $address = MerchantCustomerAddress::query()
            ->with(['customer', 'city', 'state', 'country'])
            ->whereKey($addressId)
            ->where('merchant_customer_id', $customerId)
            ->where('status', MerchantCustomerAddress::STATUS_ACTIVE)
            ->firstOrFail();

        abort_unless((int) $address->customer?->merchant_id === (int) $shop->merchant_id, 404);

        return [
            'shipping_address_id' => $address->getKey(),
            'shipping_recipient_name' => $address->recipient_name,
            'shipping_mobile_country_code' => $address->recipient_mobile_country_code,
            'shipping_mobile' => $address->recipient_mobile,
            'shipping_address_line_1' => $address->address_line_1,
            'shipping_address_line_2' => $address->address_line_2,
            'shipping_landmark' => $address->landmark,
            'shipping_city' => $address->city?->name,
            'shipping_state' => $address->state?->name,
            'shipping_country' => $address->country?->name,
            'shipping_postal_code' => $address->postal_code,
        ];
    }

    /**
     * @param array<int, array{product_variant_id: int, quantity: int, discount_type?: string|null, discount_value?: mixed}> $rows
     * @return array<int, int>
     */
    private function aggregateItems(array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one order item is required.',
            ]);
        }

        $aggregated = [];

        foreach ($rows as $index => $row) {
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($variantId < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'A valid product variant is required.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Quantity must be at least 1.',
                ]);
            }

            if (! isset($aggregated[$variantId])) {
                $aggregated[$variantId] = [
                    'quantity' => 0,
                    'discount_type' => $row['discount_type'] ?? null,
                    'discount_value' => $row['discount_value'] ?? null,
                ];
            }

            $aggregated[$variantId]['quantity'] += $quantity;
        }

        return $aggregated;
    }

    /**
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed}> $rows
     * @return array<int, ProductVariant>
     */
    private function lockVariants(Shop $shop, array $rows): array
    {
        $variants = [];

        foreach ($rows as $variantId => $row) {
            $quantity = (int) $row['quantity'];
            $variant = ProductVariant::query()
                ->with('product.primaryImage')
                ->whereKey($variantId)
                ->where('shop_id', $shop->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($variant->status !== 'active') {
                throw ValidationException::withMessages([
                    'items' => "{$variant->name} is not available for sale.",
                ]);
            }

            if ((int) $variant->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'items' => "Only {$variant->stock_quantity} unit(s) are available for {$variant->product?->product_name}.",
                ]);
            }

            $variants[$variantId] = $variant;
        }

        return $variants;
    }

    /**
     * @param array<int, int> $rows
     * @param array<int, ProductVariant> $variants
     * @return array<int, OrderItem>
     */
    private function buildItems(array $rows, array $variants): array
    {
        $items = [];

        foreach ($rows as $variantId => $row) {
            $quantity = (int) $row['quantity'];
            $variant = $variants[$variantId];
            $unitPrice = $this->money($variant->selling_price);
            $lineSubtotal = $this->money((float) $unitPrice * $quantity);
            $discount = $this->discountService->calculateLineDiscount($lineSubtotal, [
                'discount_type' => $row['discount_type'] ?? null,
                'discount_value' => $row['discount_value'] ?? null,
            ]);

            $items[] = new OrderItem([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->getKey(),
                'product_name' => $variant->product?->product_name ?? 'Product',
                'product_image' => $variant->product?->primaryImage?->image_path,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'quantity' => $quantity,
                'unit_mrp' => $this->money($variant->mrp),
                'unit_price' => $unitPrice,
                'unit_discount' => '0.00',
                'line_subtotal' => $lineSubtotal,
                'item_discount_type' => $discount['type'],
                'item_discount_value' => $discount['value'],
                'line_discount' => $discount['amount'],
                'line_tax' => '0.00',
                'line_total' => $this->money((float) $lineSubtotal - (float) $discount['amount']),
                'metadata' => null,
            ]);
        }

        return $items;
    }

    /**
     * @param array<int, ProductVariant> $variants
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed}> $rows
     */
    private function deductStock(array $variants, array $rows): void
    {
        foreach ($rows as $variantId => $row) {
            $variant = $variants[$variantId];
            $variant->forceFill([
                'stock_quantity' => (int) $variant->stock_quantity - (int) $row['quantity'],
            ])->save();
        }
    }

    /**
     * @param array<int, OrderItem> $items
     * @param array<string, mixed> $discount
     * @return array{type: string|null, value: string|null, amount: string, reason: string|null, note: string|null}
     */
    private function orderDiscount(array $items, array $discount): array
    {
        $discountableSubtotal = collect($items)->sum(
            fn (OrderItem $item): float => (float) $item->line_subtotal - (float) $item->line_discount
        );

        return $this->discountService->calculateOrderDiscount($discountableSubtotal, $discount);
    }

    /**
     * @param \Illuminate\Support\Collection<int, OrderItem> $items
     * @param array{type: string|null, value: string|null, amount: string, reason: string|null, note: string|null} $orderDiscount
     * @param array<int, array<string, mixed>> $extraRows
     * @return array<int, array<string, mixed>>
     */
    private function totalsRows(Collection $items, array $orderDiscount, array $extraRows): array
    {
        $rows = [];
        $itemDiscount = $this->money($items->sum(fn (OrderItem $item): float => (float) $item->line_discount));

        if ((float) $itemDiscount > 0) {
            $rows[] = [
                'code' => OrderTotal::CODE_ITEM_DISCOUNT,
                'title' => 'Item Discount',
                'amount' => -1 * (float) $itemDiscount,
                'sort_order' => 20,
                'source' => 'pos',
            ];
        }

        if ((float) $orderDiscount['amount'] > 0) {
            $rows[] = [
                'code' => OrderTotal::CODE_ORDER_DISCOUNT,
                'title' => 'Order Discount',
                'amount' => -1 * (float) $orderDiscount['amount'],
                'sort_order' => 30,
                'source' => 'pos',
                'metadata' => [
                    'type' => $orderDiscount['type'],
                    'value' => $orderDiscount['value'],
                    'reason' => $orderDiscount['reason'],
                    'note' => $orderDiscount['note'],
                ],
            ];
        }

        return [...$rows, ...$extraRows];
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
