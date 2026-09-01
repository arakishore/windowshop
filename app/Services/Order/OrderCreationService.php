<?php

namespace App\Services\Order;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTotal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\POS\DiscountService;
use App\Services\POS\CashRoundingService;
use App\Services\Product\ProductReturnPolicyResolver;
use App\Services\ProductAvailability\CustomerPurchaseAvailabilityGuard;
use App\Services\Promotion\Engine\Data\PromotionCalculationResult;
use App\Services\Promotion\Engine\PromotionCalculator;
use App\Services\Tax\Exceptions\TaxConfigurationException;
use App\Services\Tax\OrderTaxSnapshotFactory;
use App\Services\Tax\PricingEngine;
use Carbon\CarbonInterface;
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
        private readonly CashRoundingService $cashRoundingService,
        private readonly PricingEngine $pricingEngine,
        private readonly OrderTaxSnapshotFactory $orderTaxSnapshotFactory,
        private readonly ProductReturnPolicyResolver $returnPolicyResolver,
        private readonly CustomerPurchaseAvailabilityGuard $availabilityGuard,
        private readonly MerchantOrderStockShortageService $stockShortageService,
        private readonly PromotionCalculator $promotions,
    ) {
    }

    /**
     * @param array{
     *     shop_id: int,
     *     customer_id?: int|null,
     *     created_source?: string,
     *     fulfilment_type?: string,
     *     order_status?: string,
     *     payment_method?: string,
     *     payment_reference?: string|null,
     *     upi_txn?: string|null,
     *     terminal_id?: string|null,
     *     payment_status?: string,
     *     cash_rounding?: array{method?: string, applyTo?: array<int, string>|string},
     *     order_discount?: array{type?: string|null, value?: mixed, reason?: string|null, note?: string|null},
     *     amount_paid?: float|string|int,
     *     elapsed_seconds?: int,
     *     customer_name?: string|null,
     *     customer_mobile?: string|null,
     *     customer_email?: string|null,
     *     shipping_address_snapshot?: array<string, mixed>|null,
     *     billing_address_snapshot?: array<string, mixed>|null,
     *     remarks?: string|null,
     *     customer_order_note?: string|null,
     *     status_note?: string|null,
     *     items: array<int, array{product_variant_id: int, quantity: int, policy_snapshot?: array<string, mixed>}>,
     *     totals?: array<int, array<string, mixed>>
     * } $data
     */
    public function create(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $effectiveAt = now();
            $shop = Shop::query()->with('merchant')->findOrFail((int) $data['shop_id']);
            $rows = $this->aggregateItems($data['items'] ?? []);
            $createdSource = (string) ($data['created_source'] ?? Order::SOURCE_POS);
            $variants = $this->lockVariants($shop, $rows, $createdSource);
            $originalStockShortages = $createdSource === Order::SOURCE_STOREFRONT
                ? $this->stockShortageService->originalShortagesForRows($rows, $variants)
                : [];
            $orderStatus = (string) ($data['order_status'] ?? Order::STATUS_COMPLETED);
            $requestedPaymentStatus = isset($data['payment_status']) ? (string) $data['payment_status'] : null;
            $paymentStatus = $requestedPaymentStatus ?? Order::PAYMENT_UNPAID;
            $customerSnapshot = $this->customerSnapshot($shop, $data);
            $customer = $customerSnapshot['customer_id']
                ? Customer::query()->find((int) $customerSnapshot['customer_id'])
                : null;
            $promotionResult = $this->shouldApplyAutomaticPromotions($createdSource)
                ? $this->promotions->calculateForVariantRows($shop, $rows, $variants, $customer, $effectiveAt)
                : new PromotionCalculationResult((int) $shop->getKey(), []);
            $itemSnapshots = $this->buildItems($rows, $variants, $shop->merchant, $effectiveAt, $promotionResult);
            $items = array_map(fn (array $snapshot): OrderItem => $snapshot['item'], $itemSnapshots);
            $shippingSnapshot = $this->shippingSnapshot($shop, $data, $customerSnapshot['customer_id']);
            $billingSnapshot = $this->billingSnapshot($shop, $data, $customerSnapshot['customer_id']);
            $orderDiscount = $this->orderDiscount($items, $data['order_discount'] ?? []);

            $order = Order::query()->create([
                'order_number' => $this->orderNumberService->generate(),
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
                'customer_id' => $customerSnapshot['customer_id'],
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
                'billing_recipient_name' => $billingSnapshot['billing_recipient_name'],
                'billing_mobile_country_code' => $billingSnapshot['billing_mobile_country_code'],
                'billing_mobile' => $billingSnapshot['billing_mobile'],
                'billing_address_line_1' => $billingSnapshot['billing_address_line_1'],
                'billing_address_line_2' => $billingSnapshot['billing_address_line_2'],
                'billing_landmark' => $billingSnapshot['billing_landmark'],
                'billing_city' => $billingSnapshot['billing_city'],
                'billing_state' => $billingSnapshot['billing_state'],
                'billing_country' => $billingSnapshot['billing_country'],
                'billing_postal_code' => $billingSnapshot['billing_postal_code'],
                'order_discount_type' => $orderDiscount['type'],
                'order_discount_value' => $orderDiscount['value'],
                'order_discount_amount' => $orderDiscount['amount'],
                'order_discount_reason' => $orderDiscount['reason'],
                'order_discount_note' => $orderDiscount['note'],
                'remarks' => $data['remarks'] ?? null,
                'customer_order_note' => $this->nullableString($data['customer_order_note'] ?? null),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
                'completed_at' => $orderStatus === Order::STATUS_COMPLETED ? $effectiveAt : null,
                'cancelled_at' => $orderStatus === Order::STATUS_CANCELLED ? $effectiveAt : null,
            ]);

            foreach ($itemSnapshots as $snapshot) {
                $createdItem = $order->items()->create($this->orderItemAttributes($snapshot['item']));

                foreach ($snapshot['components'] as $componentAttributes) {
                    $createdItem->taxComponents()->create($componentAttributes);
                }
            }

            $createdItems = $order->items()->get();
            $calculated = $this->orderTotalsService->calculate(
                $createdItems,
                $this->totalsRows($createdItems, $orderDiscount, $data['totals'] ?? [], $data, $createdSource),
                $data['amount_paid'] ?? 0,
            );

            $paymentStatus = $this->resolvePaymentStatus($requestedPaymentStatus, $calculated['summary']);
            $order->forceFill(['payment_status' => $paymentStatus]);

            $this->orderTotalsService->save($order, $calculated['summary'], $calculated['rows']);
            $this->orderStatusService->recordInitial(
                $order,
                $orderStatus,
                $actor,
                $this->initialStatusNote((string) ($data['status_note'] ?? 'POS cash sale completed'), $originalStockShortages),
                $originalStockShortages === [] ? null : ['stock_shortages' => $originalStockShortages],
            );
            $this->deductStock($variants, $rows);

            return $order->load(['items', 'totals', 'statusHistories']);
        });
    }

    /**
     * @param array<string, string> $summary
     */
    private function resolvePaymentStatus(?string $requestedStatus, array $summary): string
    {
        if ($requestedStatus !== null && ! in_array($requestedStatus, $this->supportedPaymentStatuses(), true)) {
            throw ValidationException::withMessages([
                'payment_status' => 'The selected payment status is invalid.',
            ]);
        }

        $amountPaid = (float) $summary['amount_paid'];
        $grandTotal = (float) $summary['grand_total'];

        if ($requestedStatus === Order::PAYMENT_PAID && $amountPaid < $grandTotal) {
            throw ValidationException::withMessages([
                'amount_paid' => 'The amount paid must be at least the grand total for a paid sale.',
            ]);
        }

        if (in_array($requestedStatus, [Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED], true)) {
            return $requestedStatus;
        }

        if ($requestedStatus === Order::PAYMENT_PENDING) {
            return Order::PAYMENT_PENDING;
        }

        if ($grandTotal <= 0 || $amountPaid >= $grandTotal) {
            return Order::PAYMENT_PAID;
        }

        if ($amountPaid <= 0) {
            return Order::PAYMENT_UNPAID;
        }

        return Order::PAYMENT_PARTIALLY_PAID;
    }

    /**
     * @return array<int, string>
     */
    private function supportedPaymentStatuses(): array
    {
        return [
            Order::PAYMENT_UNPAID,
            Order::PAYMENT_PENDING,
            Order::PAYMENT_PARTIALLY_PAID,
            Order::PAYMENT_PAID,
            Order::PAYMENT_REFUNDED,
            Order::PAYMENT_PARTIALLY_REFUNDED,
        ];
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

        $customer = Customer::query()
            ->whereKey($customerId)
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

        if (isset($data['shipping_address_snapshot']) && is_array($data['shipping_address_snapshot'])) {
            return $this->addressSnapshotFromData($data['shipping_address_snapshot'], 'shipping');
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

        $address = CustomerAddress::query()
            ->with(['customer', 'city', 'state', 'country'])
            ->whereKey($addressId)
            ->where('customer_id', $customerId)
            ->where('status', CustomerAddress::STATUS_ACTIVE)
            ->firstOrFail();

        return [
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
     * @param array<string, mixed> $data
     * @return array{
     *     billing_recipient_name: string|null,
     *     billing_mobile_country_code: string|null,
     *     billing_mobile: string|null,
     *     billing_address_line_1: string|null,
     *     billing_address_line_2: string|null,
     *     billing_landmark: string|null,
     *     billing_city: string|null,
     *     billing_state: string|null,
     *     billing_country: string|null,
     *     billing_postal_code: string|null
     * }
     */
    private function billingSnapshot(Shop $shop, array $data, ?int $customerId): array
    {
        $empty = [
            'billing_recipient_name' => null,
            'billing_mobile_country_code' => null,
            'billing_mobile' => null,
            'billing_address_line_1' => null,
            'billing_address_line_2' => null,
            'billing_landmark' => null,
            'billing_city' => null,
            'billing_state' => null,
            'billing_country' => null,
            'billing_postal_code' => null,
        ];

        $addressId = (int) ($data['billing_address_id'] ?? 0);
        if (isset($data['billing_address_snapshot']) && is_array($data['billing_address_snapshot'])) {
            return $this->addressSnapshotFromData($data['billing_address_snapshot'], 'billing');
        }

        if ($addressId < 1) {
            return $empty;
        }

        if ($customerId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Please select a customer for billing address snapshots.',
            ]);
        }

        $address = CustomerAddress::query()
            ->with(['customer', 'city', 'state', 'country'])
            ->whereKey($addressId)
            ->where('customer_id', $customerId)
            ->where('status', CustomerAddress::STATUS_ACTIVE)
            ->firstOrFail();

        return [
            'billing_recipient_name' => $address->recipient_name,
            'billing_mobile_country_code' => $address->recipient_mobile_country_code,
            'billing_mobile' => $address->recipient_mobile,
            'billing_address_line_1' => $address->address_line_1,
            'billing_address_line_2' => $address->address_line_2,
            'billing_landmark' => $address->landmark,
            'billing_city' => $address->city?->name,
            'billing_state' => $address->state?->name,
            'billing_country' => $address->country?->name,
            'billing_postal_code' => $address->postal_code,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function addressSnapshotFromData(array $snapshot, string $prefix): array
    {
        return [
            "{$prefix}_recipient_name" => $this->nullableString($snapshot['recipient_name'] ?? null),
            "{$prefix}_mobile_country_code" => $this->nullableString($snapshot['mobile_country_code'] ?? null),
            "{$prefix}_mobile" => $this->nullableString($snapshot['mobile'] ?? null),
            "{$prefix}_address_line_1" => $this->nullableString($snapshot['address_line_1'] ?? null),
            "{$prefix}_address_line_2" => $this->nullableString($snapshot['address_line_2'] ?? null),
            "{$prefix}_landmark" => $this->nullableString($snapshot['landmark'] ?? null),
            "{$prefix}_city" => $this->nullableString($snapshot['city'] ?? null),
            "{$prefix}_state" => $this->nullableString($snapshot['state'] ?? null),
            "{$prefix}_country" => $this->nullableString($snapshot['country'] ?? null),
            "{$prefix}_postal_code" => $this->nullableString($snapshot['postal_code'] ?? null),
        ];
    }

    /**
     * @param array<int, array{product_variant_id: int, quantity: int, discount_type?: string|null, discount_value?: mixed, policy_snapshot?: array<string, mixed>}> $rows
     * @return array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed, policy_snapshot?: array<string, mixed>|null}>
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
                    'policy_snapshot' => $this->normalizePolicySnapshot($row['policy_snapshot'] ?? null),
                ];
            } else {
                $policySnapshot = $this->normalizePolicySnapshot($row['policy_snapshot'] ?? null);

                if ($policySnapshot !== null && $aggregated[$variantId]['policy_snapshot'] !== null && $policySnapshot !== $aggregated[$variantId]['policy_snapshot']) {
                    throw ValidationException::withMessages([
                        "items.{$index}.policy_snapshot" => 'Conflicting policy snapshots cannot be merged for the same product variant.',
                    ]);
                }

                $aggregated[$variantId]['policy_snapshot'] ??= $policySnapshot;
            }

            $aggregated[$variantId]['quantity'] += $quantity;
        }

        return $aggregated;
    }

    /**
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed, policy_snapshot?: array<string, mixed>|null}> $rows
     * @return array<int, ProductVariant>
     */
    private function lockVariants(Shop $shop, array $rows, string $createdSource): array
    {
        $variants = [];

        foreach ($rows as $variantId => $row) {
            $quantity = (int) $row['quantity'];
            $variant = ProductVariant::query()
                ->with([
                    'availabilityStatus',
                    'product.availabilityStatus',
                    'product.brand',
                    'product.category.parent.parent',
                    'product.collections',
                    'product.primaryImage',
                    'product.returnPolicy',
                    'product.shop.settings',
                ])
                ->whereKey($variantId)
                ->where('shop_id', $shop->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $product = $variant->product;

            if ($variant->status !== 'active') {
                throw ValidationException::withMessages([
                    'items' => "{$variant->name} is not available for sale.",
                ]);
            }

            if (! $product instanceof Product
                || (int) $product->shop_id !== (int) $shop->getKey()
                || (int) $product->merchant_id !== (int) $shop->merchant_id
            ) {
                throw ValidationException::withMessages([
                    'items' => "{$variant->name} is not available for this shop.",
                ]);
            }

            if ($createdSource === Order::SOURCE_STOREFRONT) {
                $this->availabilityGuard->assertVariantCanBePurchased($variant, $quantity);
            } elseif ((float) $variant->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'items' => "Only {$variant->stock_quantity} unit(s) are available for {$variant->product?->product_name}.",
                ]);
            }

            $variants[$variantId] = $variant;
        }

        return $variants;
    }

    /**
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed, policy_snapshot?: array<string, mixed>|null}> $rows
     * @param array<int, ProductVariant> $variants
     * @return array<int, array{item: OrderItem, components: array<int, array<string, mixed>>}>
     */
    private function buildItems(array $rows, array $variants, MerchantProfile $merchant, CarbonInterface $effectiveAt, ?PromotionCalculationResult $promotionResult = null): array
    {
        $items = [];

        foreach ($rows as $variantId => $row) {
            $quantity = (int) $row['quantity'];
            $variant = $variants[$variantId];
            $unitPrice = $this->money($variant->selling_price);
            $lineSubtotal = $this->money((float) $unitPrice * $quantity);
            $promotionAdjustment = $promotionResult?->line((int) $variantId);
            $discount = $promotionAdjustment?->hasPromotion()
                ? [
                    'type' => $promotionAdjustment->winningPromotion?->rewardType,
                    'value' => $promotionAdjustment->discountAmount(),
                    'amount' => $promotionAdjustment->discountAmount(),
                ]
                : $this->discountService->calculateLineDiscount($lineSubtotal, [
                    'discount_type' => $row['discount_type'] ?? null,
                    'discount_value' => $row['discount_value'] ?? null,
                ]);
            $product = $variant->product;

            try {
                $pricingResult = $this->pricingEngine->calculateProductLine(
                    product: $product,
                    merchant: $merchant,
                    unitPrice: $unitPrice,
                    quantity: $quantity,
                    effectiveAt: $effectiveAt,
                    discountAmount: $discount['amount'],
                );
            } catch (TaxConfigurationException $exception) {
                throw ValidationException::withMessages([
                    'items' => $exception->getMessage(),
                ]);
            }

            $taxSnapshot = $this->orderTaxSnapshotFactory->fromPricingResult($pricingResult);
            $policySnapshot = $row['policy_snapshot']
                ?? $this->policySnapshotForProduct($product);

            $item = new OrderItem(array_merge([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->getKey(),
                'product_name' => $product?->product_name ?? 'Product',
                'product_image' => $product?->primaryImage?->image_path,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'quantity' => $quantity,
                'unit_mrp' => $this->money($variant->mrp),
                'unit_price' => $unitPrice,
                'unit_discount' => $quantity > 0 ? $this->money((float) $discount['amount'] / $quantity) : '0.00',
                'item_discount_type' => $discount['type'],
                'item_discount_value' => $discount['value'],
                'refund_allowed' => $policySnapshot['refund_allowed'],
                'refund_window_days' => $policySnapshot['refund_window_days'],
                'exchange_allowed' => $policySnapshot['exchange_allowed'],
                'exchange_window_days' => $policySnapshot['exchange_window_days'],
                'metadata' => $promotionAdjustment?->metadata(),
            ], $taxSnapshot->toOrderItemAttributes()));

            $items[] = [
                'item' => $item,
                'components' => $taxSnapshot->componentAttributes(),
            ];
        }

        return $items;
    }

    /**
     * @param array<int, ProductVariant> $variants
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed, policy_snapshot?: array<string, mixed>|null}> $rows
     */
    private function deductStock(array $variants, array $rows): void
    {
        foreach ($rows as $variantId => $row) {
            $variant = $variants[$variantId];
            $variant->forceFill([
                'stock_quantity' => (float) $variant->stock_quantity - (int) $row['quantity'],
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function orderItemAttributes(OrderItem $item): array
    {
        $attributes = $item->getAttributes();

        if (isset($attributes['metadata']) && is_string($attributes['metadata'])) {
            $decoded = json_decode($attributes['metadata'], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $attributes['metadata'] = $decoded;
            }
        }

        return $attributes;
    }

    /**
     * @param array<int, array<string, mixed>> $originalStockShortages
     */
    private function initialStatusNote(string $baseNote, array $originalStockShortages): string
    {
        if ($originalStockShortages === []) {
            return $baseNote;
        }

        return trim($baseNote).PHP_EOL.PHP_EOL.$this->stockShortageService->originalShortageHistoryNote($originalStockShortages);
    }

    /**
     * @return array{refund_allowed: bool, refund_window_days: int, exchange_allowed: bool, exchange_window_days: int}
     */
    private function policySnapshotForProduct(?Product $product): array
    {
        if (! $product instanceof Product) {
            return $this->defaultPolicySnapshot();
        }

        return $this->normalizePolicySnapshot($this->returnPolicyResolver->resolve($product))
            ?? $this->defaultPolicySnapshot();
    }

    /**
     * @return array{refund_allowed: bool, refund_window_days: int, exchange_allowed: bool, exchange_window_days: int}|null
     */
    private function normalizePolicySnapshot(mixed $snapshot): ?array
    {
        if (! is_array($snapshot)) {
            return null;
        }

        foreach (['refund_allowed', 'refund_window_days', 'exchange_allowed', 'exchange_window_days'] as $key) {
            if (! array_key_exists($key, $snapshot)) {
                return null;
            }
        }

        $refundAllowed = (bool) $snapshot['refund_allowed'];
        $exchangeAllowed = (bool) $snapshot['exchange_allowed'];

        return [
            'refund_allowed' => $refundAllowed,
            'refund_window_days' => $refundAllowed ? max(0, (int) $snapshot['refund_window_days']) : 0,
            'exchange_allowed' => $exchangeAllowed,
            'exchange_window_days' => $exchangeAllowed ? max(0, (int) $snapshot['exchange_window_days']) : 0,
        ];
    }

    /**
     * @return array{refund_allowed: false, refund_window_days: 0, exchange_allowed: false, exchange_window_days: 0}
     */
    private function defaultPolicySnapshot(): array
    {
        return [
            'refund_allowed' => false,
            'refund_window_days' => 0,
            'exchange_allowed' => false,
            'exchange_window_days' => 0,
        ];
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
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function totalsRows(Collection $items, array $orderDiscount, array $extraRows, array $data, string $createdSource): array
    {
        $rows = [];
        $itemDiscount = $this->money($items->sum(fn (OrderItem $item): float => (float) $item->line_discount));

        if ((float) $itemDiscount > 0) {
            $rows[] = [
                'code' => OrderTotal::CODE_ITEM_DISCOUNT,
                'title' => $this->shouldApplyAutomaticPromotions($createdSource) ? 'Offer Discount' : 'Item Discount',
                'amount' => -1 * (float) $itemDiscount,
                'sort_order' => 20,
                'source' => $this->shouldApplyAutomaticPromotions($createdSource) ? 'promotion' : 'pos',
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

        $roundingAdjustment = $this->roundingAdjustment($items, $orderDiscount, $data);
        if ($roundingAdjustment !== 0.0) {
            $rows[] = [
                'code' => OrderTotal::CODE_ROUNDING,
                'title' => 'Round Off',
                'amount' => $roundingAdjustment,
                'sort_order' => 90,
                'source' => 'pos',
                'metadata' => [
                    'method' => $data['cash_rounding']['method'] ?? 'nearest',
                    'payment_method' => $data['payment_method'] ?? Order::PAYMENT_METHOD_CASH,
                ],
            ];
        }

        return [...$rows, ...$extraRows];
    }

    /**
     * @param Collection<int, OrderItem> $items
     * @param array{type: string|null, value: string|null, amount: string, reason: string|null, note: string|null} $orderDiscount
     * @param array<string, mixed> $data
     */
    private function roundingAdjustment(Collection $items, array $orderDiscount, array $data): float
    {
        $baseTotal = $items->sum(fn (OrderItem $item): float => (float) $item->line_total)
            - (float) $orderDiscount['amount'];

        return $this->cashRoundingService->adjustment(
            $baseTotal,
            (string) ($data['payment_method'] ?? Order::PAYMENT_METHOD_CASH),
            (array) ($data['cash_rounding'] ?? ['method' => 'nearest', 'applyTo' => []]),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function shouldApplyAutomaticPromotions(string $createdSource): bool
    {
        return in_array($createdSource, [Order::SOURCE_STOREFRONT, Order::SOURCE_CUSTOMER_APP], true);
    }
}
