<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\ProductVariant;
use App\Models\ReturnReason;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderRefundService
{
    /**
     * @return array<int, int>
     */
    public function refundableQuantities(Order $order): array
    {
        $refunded = DB::table('order_refund_items')
            ->join('order_refunds', 'order_refunds.id', '=', 'order_refund_items.order_refund_id')
            ->where('order_refunds.order_id', $order->getKey())
            ->select('order_refund_items.order_item_id', DB::raw('SUM(order_refund_items.quantity) as quantity'))
            ->groupBy('order_refund_items.order_item_id')
            ->pluck('quantity', 'order_item_id');

        return $order->items
            ->mapWithKeys(fn (OrderItem $item): array => [
                $item->getKey() => max(0, (int) $item->quantity - (int) ($refunded[$item->getKey()] ?? 0)),
            ])
            ->all();
    }

    /**
     * @param array{
     *     return_reason_id: int,
     *     refund_method: string,
     *     notes?: string|null,
     *     items: array<int|string, array{quantity?: int|string|null, restock?: mixed, do_not_restock?: mixed}>
     * } $data
     */
    public function create(Order $order, array $data, User $actor): OrderRefund
    {
        return DB::transaction(function () use ($order, $data, $actor): OrderRefund {
            $order = Order::query()
                ->with(['items'])
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $refundable = $this->refundableQuantities($order);
            $rows = $this->selectedRows($order, $data['items'] ?? [], $refundable);
            $reason = ReturnReason::query()
                ->whereKey((int) $data['return_reason_id'])
                ->where('merchant_id', $order->merchant_id)
                ->where('status', ReturnReason::STATUS_ACTIVE)
                ->firstOrFail();

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'items' => 'Choose at least one line quantity to refund.',
                ]);
            }

            $refundSubtotal = $this->money(array_sum(array_column($rows, 'line_total')));
            $refundTax = $this->money(array_sum(array_column($rows, 'line_tax')));
            $refundTotal = $this->money((float) $refundSubtotal + (float) $refundTax);

            $refund = OrderRefund::query()->create([
                'refund_number' => $this->generateRefundNumber(),
                'order_id' => $order->getKey(),
                'merchant_id' => $order->merchant_id,
                'shop_id' => $order->shop_id,
                'return_reason_id' => $reason->getKey(),
                'reason_name' => $reason->name,
                'refund_method' => $data['refund_method'],
                'refund_subtotal' => $refundSubtotal,
                'refund_tax' => $refundTax,
                'refund_total' => $refundTotal,
                'status' => OrderRefund::STATUS_COMPLETED,
                'created_by' => $actor->getKey(),
                'metadata' => [
                    'notes' => $this->nullableString($data['notes'] ?? null),
                ],
            ]);

            foreach ($rows as $row) {
                $refund->items()->create([
                    'order_item_id' => $row['item']->getKey(),
                    'product_variant_id' => $row['item']->product_variant_id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['item']->unit_price,
                    'line_tax' => $row['line_tax'],
                    'line_total' => $row['line_total'],
                    'restocked' => $row['restocked'],
                ]);

                if ($row['restocked'] && $row['item']->product_variant_id) {
                    ProductVariant::query()
                        ->whereKey($row['item']->product_variant_id)
                        ->increment('stock_quantity', $row['quantity']);
                }
            }

            $this->updateOrderPaymentStatus($order);

            $order->statusHistories()->create([
                'from_status' => $order->order_status,
                'to_status' => $order->order_status,
                'notes' => 'Refund processed',
                'changed_by' => $actor->getKey(),
                'metadata' => [
                    'refund_number' => $refund->refund_number,
                    'refund_total' => $refundTotal,
                    'notes' => $this->nullableString($data['notes'] ?? null),
                ],
                'created_at' => now(),
            ]);

            return $refund->load(['items.orderItem']);
        });
    }

    /**
     * @param array<int|string, array{quantity?: int|string|null, restock?: mixed, do_not_restock?: mixed}> $submitted
     * @param array<int, int> $refundable
     * @return array<int, array{item: OrderItem, quantity: int, line_tax: string, line_total: string, restocked: bool}>
     */
    private function selectedRows(Order $order, array $submitted, array $refundable): array
    {
        $rows = [];

        foreach ($order->items as $item) {
            $itemId = $item->getKey();
            $quantity = (int) ($submitted[$itemId]['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            if ($quantity > ($refundable[$itemId] ?? 0)) {
                throw ValidationException::withMessages([
                    "items.{$itemId}.quantity" => 'Refund quantity cannot exceed the remaining refundable quantity.',
                ]);
            }

            $ratio = $quantity / max(1, (int) $item->quantity);
            $rows[] = [
                'item' => $item,
                'quantity' => $quantity,
                'line_tax' => $this->money((float) $item->line_tax * $ratio),
                'line_total' => $this->money((float) $item->line_total * $ratio),
                'restocked' => $this->shouldRestock($submitted[$itemId] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function shouldRestock(array $row): bool
    {
        if (array_key_exists('restock', $row)) {
            return (bool) $row['restock'];
        }

        return empty($row['do_not_restock']);
    }

    private function updateOrderPaymentStatus(Order $order): void
    {
        $refundedTotal = (float) $order->refunds()->sum('refund_total');
        $grandTotal = (float) $order->grand_total;

        $order->forceFill([
            'payment_status' => $refundedTotal >= $grandTotal
                ? Order::PAYMENT_REFUNDED
                : Order::PAYMENT_PARTIALLY_REFUNDED,
            'updated_at' => now(),
        ])->save();
    }

    private function generateRefundNumber(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'REFUND-'.$date->format('Ymd').'-';

        return Cache::lock('refund-number:'.$date->format('Ymd'), 10)->block(5, function () use ($prefix): string {
            return DB::transaction(function () use ($prefix): string {
                $latest = OrderRefund::query()
                    ->where('refund_number', 'like', $prefix.'%')
                    ->orderByDesc('refund_number')
                    ->lockForUpdate()
                    ->value('refund_number');

                $next = $latest ? ((int) substr($latest, -6)) + 1 : 1;

                return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            });
        });
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
