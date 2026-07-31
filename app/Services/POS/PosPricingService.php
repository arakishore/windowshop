<?php

namespace App\Services\POS;

use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTotal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Services\Order\OrderTotalsService;
use App\Services\Tax\Exceptions\TaxConfigurationException;
use App\Services\Tax\OrderTaxSnapshotFactory;
use App\Services\Tax\PricingEngine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PosPricingService
{
    public function __construct(
        private readonly DiscountService $discountService,
        private readonly CashRoundingService $cashRoundingService,
        private readonly PricingEngine $pricingEngine,
        private readonly OrderTaxSnapshotFactory $orderTaxSnapshotFactory,
        private readonly OrderTotalsService $orderTotalsService,
    ) {
    }

    /**
     * @param array{
     *     items: array<int, array{product_variant_id: int, quantity: int, discount_type?: string|null, discount_value?: mixed}>,
     *     order_discount?: array{type?: string|null, value?: mixed, reason?: string|null, note?: string|null}|null,
     *     payment_method?: string|null,
     *     amount_paid?: mixed
     * } $data
     * @param array{method?: string, applyTo?: array<int, string>|string} $cashRounding
     * @return array<string, mixed>
     */
    public function price(Shop $shop, array $data, array $cashRounding, CarbonInterface $effectiveAt): array
    {
        $shop->loadMissing('merchant.taxSetting');
        $rows = $this->aggregateItems($data['items'] ?? []);
        $variants = $this->variants($shop, $rows);
        $items = $this->buildItems($rows, $variants, $shop->merchant, $effectiveAt);
        $orderDiscount = $this->orderDiscount($items, $data['order_discount'] ?? []);
        $paymentMethod = (string) ($data['payment_method'] ?? Order::PAYMENT_METHOD_CASH);

        $calculated = $this->orderTotalsService->calculate(
            $items,
            $this->totalsRows(collect($items), $orderDiscount, $paymentMethod, $cashRounding),
            $data['amount_paid'] ?? 0,
        );

        $summary = $calculated['summary'];
        unset($summary['_line_payable_total'], $summary['_line_discount_total'], $summary['_line_tax_total']);

        return [
            'tax_display_enabled' => (bool) $shop->merchant?->taxSetting?->tax_enabled,
            'has_tax_amount' => (float) $summary['tax_total'] > 0,
            'items' => array_map(fn (OrderItem $item): array => $this->itemPayload($item), $items),
            'summary' => $summary,
            'totals' => $calculated['rows'],
            'order_discount' => $orderDiscount,
            'payment_method' => $paymentMethod,
            'calculated_at' => $effectiveAt->toDateTimeString(),
        ];
    }

    /**
     * @param array<int, array{product_variant_id?: int, quantity?: int, discount_type?: string|null, discount_value?: mixed}> $rows
     * @return array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed}>
     */
    private function aggregateItems(array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one cart item is required.',
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
    private function variants(Shop $shop, array $rows): array
    {
        $variants = [];

        foreach ($rows as $variantId => $row) {
            $variant = ProductVariant::query()
                ->with(['product.category', 'product.primaryImage'])
                ->whereKey($variantId)
                ->where('shop_id', $shop->getKey())
                ->firstOrFail();
            $product = $variant->product;

            if ($variant->status !== 'active') {
                throw ValidationException::withMessages([
                    'items' => "{$variant->name} is not available for sale.",
                ]);
            }

            if ((int) $variant->stock_quantity < (int) $row['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Only {$variant->stock_quantity} unit(s) are available for {$variant->product?->product_name}.",
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

            $variants[$variantId] = $variant;
        }

        return $variants;
    }

    /**
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed}> $rows
     * @param array<int, ProductVariant> $variants
     * @return array<int, OrderItem>
     */
    private function buildItems(array $rows, array $variants, MerchantProfile $merchant, CarbonInterface $effectiveAt): array
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

            $items[] = new OrderItem(array_merge([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->getKey(),
                'product_name' => $product?->product_name ?? 'Product',
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'quantity' => $quantity,
                'unit_mrp' => $this->money($variant->mrp),
                'unit_price' => $unitPrice,
                'unit_discount' => '0.00',
                'item_discount_type' => $discount['type'],
                'item_discount_value' => $discount['value'],
                'metadata' => null,
            ], $taxSnapshot->toOrderItemAttributes()));
        }

        return $items;
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
     * @param Collection<int, OrderItem> $items
     * @param array{type: string|null, value: string|null, amount: string, reason: string|null, note: string|null} $orderDiscount
     * @param array{method?: string, applyTo?: array<int, string>|string} $cashRounding
     * @return array<int, array<string, mixed>>
     */
    private function totalsRows(Collection $items, array $orderDiscount, string $paymentMethod, array $cashRounding): array
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

        $roundingAdjustment = $this->cashRoundingService->adjustment(
            $items->sum(fn (OrderItem $item): float => (float) $item->line_total) - (float) $orderDiscount['amount'],
            $paymentMethod,
            $cashRounding,
        );

        if ($roundingAdjustment !== 0.0) {
            $rows[] = [
                'code' => OrderTotal::CODE_ROUNDING,
                'title' => 'Round Off',
                'amount' => $roundingAdjustment,
                'sort_order' => 90,
                'source' => 'pos',
                'metadata' => [
                    'method' => $cashRounding['method'] ?? 'nearest',
                    'payment_method' => $paymentMethod,
                ],
            ];
        }

        return $rows;
    }

    private function itemPayload(OrderItem $item): array
    {
        return [
            'product_variant_id' => (int) $item->product_variant_id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'quantity' => (int) $item->quantity,
            'unit_mrp' => (string) $item->unit_mrp,
            'unit_price' => (string) $item->unit_price,
            'line_subtotal' => (string) $item->line_subtotal,
            'line_discount' => (string) $item->line_discount,
            'tax_enabled' => (bool) $item->tax_enabled,
            'tax_resolution_source' => $item->tax_resolution_source,
            'tax_class_code' => $item->tax_class_code,
            'tax_class_name' => $item->tax_class_name,
            'tax_rate_name' => $item->tax_rate_name,
            'tax_rate' => $item->tax_rate ? (string) $item->tax_rate : null,
            'price_mode' => $item->price_mode,
            'taxable_amount' => $item->taxable_amount ? (string) $item->taxable_amount : null,
            'line_tax' => (string) $item->line_tax,
            'line_total' => (string) $item->line_total,
        ];
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
