<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;

class OrderInventoryService
{
    /**
     * @return array<int, array{product_variant_id: int, quantity: int}>
     */
    public function restoreForCancellation(Order $order): array
    {
        $restored = [];
        $items = $order->items()->whereNotNull('product_variant_id')->get();

        foreach ($this->quantitiesByVariant($items) as $variantId => $quantity) {
            ProductVariant::query()
                ->whereKey($variantId)
                ->where('shop_id', $order->shop_id)
                ->lockForUpdate()
                ->firstOrFail()
                ->increment('stock_quantity', $quantity);

            $restored[] = [
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ];
        }

        return $restored;
    }

    /**
     * @param iterable<int, OrderItem> $items
     * @return array<int, int>
     */
    private function quantitiesByVariant(iterable $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $variantId = (int) $item->product_variant_id;
            $quantity = (int) $item->quantity;

            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }

            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + $quantity;
        }

        return $quantities;
    }
}
