<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductVariant;
use App\Services\ProductAvailability\ProductAvailabilityResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MerchantOrderStockShortageService
{
    public function __construct(private readonly ProductAvailabilityResolver $availabilityResolver)
    {
    }

    /**
     * @return array{
     *     has_shortage: bool,
     *     has_ambiguous_shortage: bool,
     *     total_short_quantity: float,
     *     summary: string|null,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function forOrder(Order $order): array
    {
        $shortages = $this->forOrders(new EloquentCollection([$order]));

        return $shortages[$order->getKey()] ?? $this->emptyResult();
    }

    /**
     * @param iterable<int, Order> $orders
     * @return array<int, array{
     *     has_shortage: bool,
     *     has_ambiguous_shortage: bool,
     *     total_short_quantity: float,
     *     summary: string|null,
     *     items: array<int, array<string, mixed>>
     * }>
     */
    public function forOrders(iterable $orders): array
    {
        $orders = $orders instanceof EloquentCollection ? $orders : new EloquentCollection(collect($orders)->all());

        $orders->loadMissing([
            'items.variant.availabilityStatus',
            'items.variant.product.availabilityStatus',
        ]);

        $variantIds = $orders
            ->flatMap(fn (Order $order): Collection => $order->items->pluck('product_variant_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $demandCounts = $this->openDemandCounts($variantIds);
        $results = [];

        foreach ($orders as $order) {
            $results[$order->getKey()] = $this->buildOrderResult($order, $demandCounts);
        }

        return $results;
    }

    /**
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed, policy_snapshot?: array<string, mixed>|null}> $rows
     * @param array<int, ProductVariant> $variants
     * @return array<int, array<string, mixed>>
     */
    public function originalShortagesForRows(array $rows, array $variants): array
    {
        $shortages = [];

        foreach ($rows as $variantId => $row) {
            $variant = $variants[$variantId] ?? null;
            if (! $variant instanceof ProductVariant) {
                continue;
            }

            $variant->loadMissing(['availabilityStatus', 'product.availabilityStatus']);
            $availability = $this->availabilityResolver->resolve($variant);
            $code = $availability['availability_code'];

            if (! $this->isShortageSellingPolicy($code)) {
                continue;
            }

            $orderedQuantity = (float) ($row['quantity'] ?? 0);
            $availableStock = (float) $variant->getRawOriginal('stock_quantity');
            $shortQuantity = max(0, $orderedQuantity - max(0, $availableStock));

            if ($orderedQuantity <= 0 || $shortQuantity <= 0) {
                continue;
            }

            $shortages[] = [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->getKey(),
                'product_name' => $variant->product?->product_name ?? 'Product',
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'ordered_quantity' => $orderedQuantity,
                'available_stock' => $availableStock,
                'short_quantity' => $shortQuantity,
                'availability_status' => $code,
            ];
        }

        return $shortages;
    }

    /**
     * @param array<int, array<string, mixed>> $shortages
     */
    public function originalShortageHistoryNote(array $shortages): string
    {
        $lines = ['Order placed with insufficient stock.'];

        foreach ($shortages as $shortage) {
            $lines[] = 'Product: '.($shortage['product_name'] ?? 'Product');
            $lines[] = 'Ordered Qty: '.$this->formatQuantity($shortage['ordered_quantity'] ?? 0);
            $lines[] = 'Available Stock: '.$this->formatQuantity($shortage['available_stock'] ?? 0);
            $lines[] = 'Short Qty: '.$this->formatQuantity($shortage['short_quantity'] ?? 0);
            $lines[] = 'Availability Status: '.($shortage['availability_status'] ?? '-');
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<int, array{demand_rows: int, quantity: float}> $demandCounts
     * @return array{
     *     has_shortage: bool,
     *     has_ambiguous_shortage: bool,
     *     total_short_quantity: float,
     *     summary: string|null,
     *     items: array<int, array<string, mixed>>
     * }
     */
    private function buildOrderResult(Order $order, array $demandCounts): array
    {
        if (in_array($order->order_status, $this->terminalStatuses(), true)) {
            return $this->emptyResult();
        }

        $items = [];

        foreach ($order->items as $item) {
            $shortage = $this->buildItemResult($item, $demandCounts);

            if ($shortage !== null) {
                $items[] = $shortage;
            }
        }

        if ($items === []) {
            return $this->emptyResult();
        }

        $hasAmbiguousShortage = collect($items)->contains(fn (array $item): bool => (bool) ($item['is_ambiguous'] ?? false));
        $totalShortQuantity = (float) collect($items)->sum(fn (array $item): float => (float) ($item['short_quantity'] ?? 0));
        $summary = $hasAmbiguousShortage
            ? 'Outstanding stock demand exists'
            : $this->formatQuantity($totalShortQuantity).' '.str('unit')->plural((int) $totalShortQuantity).' short';

        return [
            'has_shortage' => true,
            'has_ambiguous_shortage' => $hasAmbiguousShortage,
            'total_short_quantity' => $totalShortQuantity,
            'summary' => $summary,
            'items' => $items,
        ];
    }

    /**
     * @param array<int, array{demand_rows: int, quantity: float}> $demandCounts
     * @return array<string, mixed>|null
     */
    private function buildItemResult(OrderItem $item, array $demandCounts): ?array
    {
        $variant = $item->variant;

        if (! $variant instanceof ProductVariant) {
            return null;
        }

        $variant->loadMissing(['availabilityStatus', 'product.availabilityStatus']);
        $availability = $this->availabilityResolver->resolve($variant);

        if (! $this->isShortageSellingPolicy($availability['availability_code'])) {
            return null;
        }

        $orderedQuantity = (float) $item->quantity;
        $currentStock = (float) $variant->stock_quantity;

        if ($orderedQuantity <= 0 || $currentStock >= 0) {
            return null;
        }

        $demand = $demandCounts[(int) $variant->getKey()] ?? ['demand_rows' => 1, 'quantity' => $orderedQuantity];
        $isAmbiguous = (int) $demand['demand_rows'] > 1;
        $shortQuantity = $isAmbiguous ? null : min($orderedQuantity, abs($currentStock));

        return [
            'order_item_id' => $item->getKey(),
            'product_id' => $item->product_id,
            'product_variant_id' => $variant->getKey(),
            'product_name' => $item->product_name ?: ($variant->product?->product_name ?? 'Product'),
            'variant_name' => $item->variant_name ?: $variant->name,
            'sku' => $item->sku ?: $variant->sku,
            'availability_status' => $availability['availability_code'],
            'ordered_quantity' => $orderedQuantity,
            'current_stock' => $currentStock,
            'display_available_stock' => max(0, $currentStock),
            'short_quantity' => $shortQuantity,
            'is_ambiguous' => $isAmbiguous,
            'message' => $isAmbiguous
                ? 'Outstanding stock demand exists for this item.'
                : 'Current stock is short by '.$this->formatQuantity((float) $shortQuantity).' '.str('unit')->plural((int) $shortQuantity).'.',
        ];
    }

    /**
     * @param array<int, int> $variantIds
     * @return array<int, array{demand_rows: int, quantity: float}>
     */
    private function openDemandCounts(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return OrderItem::query()
            ->select([
                'order_items.product_variant_id',
                DB::raw('COUNT(*) as demand_rows'),
                DB::raw('SUM(order_items.quantity) as quantity'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_variant_id', $variantIds)
            ->whereNotIn('orders.order_status', $this->terminalStatuses())
            ->whereNull('orders.deleted_at')
            ->groupBy('order_items.product_variant_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->product_variant_id => [
                    'demand_rows' => (int) $row->demand_rows,
                    'quantity' => (float) $row->quantity,
                ],
            ])
            ->all();
    }

    private function isShortageSellingPolicy(?string $code): bool
    {
        return in_array($code, [
            ProductAvailabilityStatus::CODE_BACKORDER,
            ProductAvailabilityStatus::CODE_PREORDER,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function terminalStatuses(): array
    {
        return [
            Order::STATUS_CANCELLED,
            Order::STATUS_COMPLETED,
        ];
    }

    /**
     * @return array{
     *     has_shortage: bool,
     *     has_ambiguous_shortage: bool,
     *     total_short_quantity: float,
     *     summary: string|null,
     *     items: array<int, array<string, mixed>>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'has_shortage' => false,
            'has_ambiguous_shortage' => false,
            'total_short_quantity' => 0.0,
            'summary' => null,
            'items' => [],
        ];
    }

    private function formatQuantity(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) ($value ?? 0), 3, '.', ''), '0'), '.') ?: '0';
    }
}
