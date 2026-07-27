<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderExchange;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderExchangeService
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function exchangeableQuantities(Order $order): array
    {
        $refunded = DB::table('order_refund_items')
            ->join('order_refunds', 'order_refunds.id', '=', 'order_refund_items.order_refund_id')
            ->where('order_refunds.order_id', $order->getKey())
            ->select('order_refund_items.order_item_id', DB::raw('SUM(order_refund_items.quantity) as quantity'))
            ->groupBy('order_refund_items.order_item_id')
            ->pluck('quantity', 'order_item_id');

        $exchanged = DB::table('order_exchange_return_items')
            ->join('order_exchanges', 'order_exchanges.id', '=', 'order_exchange_return_items.order_exchange_id')
            ->where('order_exchanges.original_order_id', $order->getKey())
            ->where('order_exchanges.status', OrderExchange::STATUS_COMPLETED)
            ->select('order_exchange_return_items.order_item_id', DB::raw('SUM(order_exchange_return_items.quantity) as quantity'))
            ->groupBy('order_exchange_return_items.order_item_id')
            ->pluck('quantity', 'order_item_id');

        return $order->items
            ->mapWithKeys(fn (OrderItem $item): array => [
                $item->getKey() => max(
                    0,
                    (int) $item->quantity
                    - (int) ($refunded[$item->getKey()] ?? 0)
                    - (int) ($exchanged[$item->getKey()] ?? 0),
                ),
            ])
            ->all();
    }

    /**
     * @param array{
     *     returned_items: array<int|string, array{quantity?: int|string|null, restock?: mixed, do_not_restock?: mixed}>,
     *     replacement_items: array<int, array{product_variant_id?: int|string|null, quantity?: int|string|null}>,
     *     settlement_method?: string|null,
     *     notes?: string|null
     * } $data
     */
    public function create(Order $order, array $data, User $actor): OrderExchange
    {
        return DB::transaction(function () use ($order, $data, $actor): OrderExchange {
            $order = Order::query()
                ->with(['items'])
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $exchangeable = $this->exchangeableQuantities($order);
            $returnedRows = $this->selectedReturnRows($order, $data['returned_items'] ?? [], $exchangeable);
            $replacementRows = $this->selectedReplacementRows($data['replacement_items'] ?? []);

            if ($returnedRows === []) {
                throw ValidationException::withMessages([
                    'returned_items' => 'Choose at least one original line quantity to exchange.',
                ]);
            }

            if ($replacementRows === []) {
                throw ValidationException::withMessages([
                    'replacement_items' => 'Choose at least one replacement item.',
                ]);
            }

            $returnedTotal = $this->money(array_sum(array_column($returnedRows, 'settlement_line_total')));
            $settlementMethod = $this->settlementPaymentMethod((string) ($data['settlement_method'] ?? $order->payment_method));
            $replacementOrder = $this->orderCreationService->create([
                'shop_id' => $order->shop_id,
                'created_source' => Order::SOURCE_EXCHANGE_REPLACEMENT,
                'fulfilment_type' => Order::FULFILMENT_COUNTER,
                'customer_id' => $order->customer_id,
                'payment_method' => $settlementMethod,
                'currency_code' => $order->currency_code,
                'cash_rounding' => ['method' => 'none', 'applyTo' => []],
                'order_status' => Order::STATUS_COMPLETED,
                // Operational paid status only: actual newly collected/refunded exchange money
                // is stored on order_exchanges.amount_collected / amount_refunded / credit_adjustment_amount.
                // Collection and sales reports must exclude created_source=exchange_replacement.
                'amount_paid' => $this->replacementEstimate($replacementRows),
                'remarks' => 'Replacement for exchange against '.$order->order_number,
                'items' => $replacementRows,
            ], $actor);

            $replacementTotal = $this->money($replacementOrder->grand_total);
            $difference = $this->money((float) $replacementTotal - (float) $returnedTotal);
            $settlement = $this->settlement($order, $difference, $settlementMethod);

            $exchange = OrderExchange::query()->create([
                'exchange_number' => $this->generateExchangeNumber(),
                'original_order_id' => $order->getKey(),
                'replacement_order_id' => $replacementOrder->getKey(),
                'merchant_id' => $order->merchant_id,
                'shop_id' => $order->shop_id,
                'returned_total' => $returnedTotal,
                'replacement_total' => $replacementTotal,
                'difference_amount' => $difference,
                'amount_collected' => $settlement['amount_collected'],
                'amount_refunded' => $settlement['amount_refunded'],
                'credit_adjustment_amount' => $settlement['credit_adjustment_amount'],
                'settlement_type' => $settlement['settlement_type'],
                'settlement_method' => $settlement['settlement_method'],
                'status' => OrderExchange::STATUS_COMPLETED,
                'created_by' => $actor->getKey(),
                'metadata' => [
                    'notes' => $this->nullableString($data['notes'] ?? null),
                    'original_order_number' => $order->order_number,
                    'replacement_order_number' => $replacementOrder->order_number,
                ],
            ]);

            foreach ($returnedRows as $row) {
                $exchange->items()->create([
                    'order_item_id' => $row['item']->getKey(),
                    'product_variant_id' => $row['item']->product_variant_id,
                    'quantity' => $row['quantity'],
                    'unit_return_value' => $row['unit_return_value'],
                    'line_tax' => $row['line_tax'],
                    'line_total' => $row['line_total'],
                    'restocked' => $row['restocked'],
                    'metadata' => [
                        'original_unit_price' => $row['item']->unit_price,
                        'original_line_discount' => $row['item']->line_discount,
                        'settlement_line_total' => $row['settlement_line_total'],
                        'tax_included_in_returned_total' => (float) $row['line_tax'] > 0,
                    ],
                ]);

                if ($row['restocked'] && $row['item']->product_variant_id) {
                    ProductVariant::query()
                        ->whereKey($row['item']->product_variant_id)
                        ->lockForUpdate()
                        ->firstOrFail()
                        ->increment('stock_quantity', $row['quantity']);
                }
            }

            $order->statusHistories()->create([
                'from_status' => $order->order_status,
                'to_status' => $order->order_status,
                'notes' => 'Exchange processed',
                'changed_by' => $actor->getKey(),
                'metadata' => [
                    'exchange_number' => $exchange->exchange_number,
                    'replacement_order_number' => $replacementOrder->order_number,
                    'returned_total' => $returnedTotal,
                    'replacement_total' => $replacementTotal,
                    'difference_amount' => $difference,
                    'settlement_type' => $settlement['settlement_type'],
                    'notes' => $this->nullableString($data['notes'] ?? null),
                ],
            ]);

            return $exchange->load(['items.orderItem', 'replacementOrder.items', 'originalOrder']);
        });
    }

    /**
     * @param array<int|string, array{quantity?: int|string|null, restock?: mixed, do_not_restock?: mixed}> $submitted
     * @param array<int, int> $exchangeable
     * @return array<int, array{item: OrderItem, quantity: int, unit_return_value: string, line_tax: string, line_total: string, settlement_line_total: string, restocked: bool}>
     */
    private function selectedReturnRows(Order $order, array $submitted, array $exchangeable): array
    {
        $rows = [];

        foreach ($order->items as $item) {
            $itemId = $item->getKey();
            $quantity = (int) ($submitted[$itemId]['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            if ($quantity > ($exchangeable[$itemId] ?? 0)) {
                throw ValidationException::withMessages([
                    "returned_items.{$itemId}.quantity" => 'Exchange quantity cannot exceed the remaining exchangeable quantity.',
                ]);
            }

            $values = $this->returnedLineValues($item, $quantity);

            $rows[] = [
                'item' => $item,
                'quantity' => $quantity,
                'unit_return_value' => $values['unit_return_value'],
                'line_tax' => $values['line_tax'],
                'line_total' => $values['line_total'],
                'settlement_line_total' => $values['settlement_line_total'],
                'restocked' => $this->shouldRestock($submitted[$itemId] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * `order_items.line_total` is tax-exclusive in the current order model;
     * `order_items.line_tax` is stored separately and is zero in today's POS flow.
     * Keep the tax split explicit so GST can be included in exchange settlement
     * without either omitting tax or adding it twice.
     *
     * @return array{unit_return_value: string, line_tax: string, line_total: string, settlement_line_total: string}
     */
    public function returnedLineValues(OrderItem $item, int $quantity): array
    {
        $quantity = max(0, $quantity);
        $orderedQuantity = max(1, (int) $item->quantity);
        $ratio = $quantity / $orderedQuantity;
        $unitReturnValue = $this->money((float) $item->line_total / $orderedQuantity);
        $lineTotal = $this->money((float) $unitReturnValue * $quantity);
        $lineTax = $this->money((float) $item->line_tax * $ratio);

        return [
            'unit_return_value' => $unitReturnValue,
            'line_tax' => $lineTax,
            'line_total' => $lineTotal,
            'settlement_line_total' => $this->money((float) $lineTotal + (float) $lineTax),
        ];
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

    /**
     * @param array<int, array{product_variant_id?: int|string|null, quantity?: int|string|null}> $submitted
     * @return array<int, array{product_variant_id: int, quantity: int}>
     */
    private function selectedReplacementRows(array $submitted): array
    {
        $rows = [];

        foreach ($submitted as $index => $row) {
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($variantId < 1 && $quantity < 1) {
                continue;
            }

            if ($variantId < 1) {
                throw ValidationException::withMessages([
                    "replacement_items.{$index}.product_variant_id" => 'Choose a replacement item.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "replacement_items.{$index}.quantity" => 'Replacement quantity must be at least 1.',
                ]);
            }

            $rows[] = [
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array{product_variant_id: int, quantity: int}> $rows
     */
    private function replacementEstimate(array $rows): string
    {
        $variants = ProductVariant::query()
            ->whereIn('id', array_column($rows, 'product_variant_id'))
            ->pluck('selling_price', 'id');

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) ($variants[$row['product_variant_id']] ?? 0) * (int) $row['quantity'];
        }

        return $this->money($total);
    }

    /**
     * @return array{settlement_type: string, settlement_method: string|null, amount_collected: string, amount_refunded: string, credit_adjustment_amount: string}
     */
    private function settlement(Order $order, string $difference, string $settlementMethod): array
    {
        $differenceValue = (float) $difference;
        $isCreditSale = $order->payment_method === Order::PAYMENT_METHOD_CREDIT
            || in_array($order->payment_status, [Order::PAYMENT_UNPAID, Order::PAYMENT_PARTIALLY_PAID], true);

        if ($differenceValue > 0) {
            return [
                'settlement_type' => OrderExchange::SETTLEMENT_COLLECT_EXTRA,
                'settlement_method' => $settlementMethod,
                'amount_collected' => $this->money($differenceValue),
                'amount_refunded' => '0.00',
                'credit_adjustment_amount' => '0.00',
            ];
        }

        if ($differenceValue < 0 && $isCreditSale) {
            return [
                'settlement_type' => OrderExchange::SETTLEMENT_CREDIT_ADJUSTMENT,
                'settlement_method' => null,
                'amount_collected' => '0.00',
                'amount_refunded' => '0.00',
                'credit_adjustment_amount' => $this->money(abs($differenceValue)),
            ];
        }

        if ($differenceValue < 0) {
            return [
                'settlement_type' => OrderExchange::SETTLEMENT_REFUND_BALANCE,
                'settlement_method' => $settlementMethod,
                'amount_collected' => '0.00',
                'amount_refunded' => $this->money(abs($differenceValue)),
                'credit_adjustment_amount' => '0.00',
            ];
        }

        return [
            'settlement_type' => OrderExchange::SETTLEMENT_EVEN,
            'settlement_method' => null,
            'amount_collected' => '0.00',
            'amount_refunded' => '0.00',
            'credit_adjustment_amount' => '0.00',
        ];
    }

    private function settlementPaymentMethod(string $method): string
    {
        return in_array($method, [
            Order::PAYMENT_METHOD_CASH,
            Order::PAYMENT_METHOD_UPI,
            Order::PAYMENT_METHOD_CARD,
            Order::PAYMENT_METHOD_CREDIT,
            'bank_transfer',
            'cheque',
        ], true) ? $method : Order::PAYMENT_METHOD_CASH;
    }

    private function generateExchangeNumber(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'EXCH-'.$date->format('Ymd').'-';

        return Cache::lock('exchange-number:'.$date->format('Ymd'), 10)->block(5, function () use ($prefix): string {
            return DB::transaction(function () use ($prefix): string {
                $latest = OrderExchange::query()
                    ->where('exchange_number', 'like', $prefix.'%')
                    ->orderByDesc('exchange_number')
                    ->lockForUpdate()
                    ->value('exchange_number');

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
