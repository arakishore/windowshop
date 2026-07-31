<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTotal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderTotalsService
{
    /**
     * @param Collection<int, OrderItem>|array<int, OrderItem> $items
     * @param array<int, array<string, mixed>> $extraRows
     * @return array{summary: array<string, string>, rows: array<int, array<string, mixed>>}
     */
    public function calculate(Collection|array $items, array $extraRows = [], float|string|int $amountPaid = 0): array
    {
        $items = collect($items);
        $subtotal = $this->money($items->sum(fn (OrderItem $item): float => (float) $item->line_subtotal));
        $lineDiscount = $this->money($items->sum(fn (OrderItem $item): float => (float) $item->line_discount));
        $lineTax = $this->money($items->sum(fn (OrderItem $item): float => (float) $item->line_tax));
        $linePayableTotal = $this->money($items->sum(fn (OrderItem $item): float => (float) $item->line_total));

        $rows = [[
            'code' => OrderTotal::CODE_SUBTOTAL,
            'title' => 'Subtotal',
            'amount' => $subtotal,
            'sort_order' => 10,
            'source' => 'system',
            'metadata' => null,
        ]];

        foreach ($extraRows as $row) {
            $rows[] = $this->normalizeRow($row);
        }

        $discountRows = collect($rows)
            ->filter(fn (array $row): bool => $row['code'] !== OrderTotal::CODE_ITEM_DISCOUNT
                && (str_contains((string) $row['code'], 'discount') || str_contains((string) $row['code'], 'coupon')));
        $taxRows = collect($rows)
            ->filter(fn (array $row): bool => in_array($row['code'], [OrderTotal::CODE_TAX, OrderTotal::CODE_CGST, OrderTotal::CODE_SGST, OrderTotal::CODE_IGST], true));
        $shippingRows = collect($rows)
            ->filter(fn (array $row): bool => in_array($row['code'], [OrderTotal::CODE_SHIPPING], true));
        $roundingRows = collect($rows)
            ->filter(fn (array $row): bool => $row['code'] === OrderTotal::CODE_ROUNDING);

        $discountTotal = $this->money((float) $lineDiscount + abs($discountRows->sum(fn (array $row): float => min(0, (float) $row['amount']))));
        $shippingTotal = $this->money($shippingRows->sum(fn (array $row): float => (float) $row['amount']));
        $extraTaxTotal = $this->money($taxRows->sum(fn (array $row): float => (float) $row['amount']));
        $taxTotal = $this->money((float) $lineTax + (float) $extraTaxTotal);
        $roundingAdjustment = $this->money($roundingRows->sum(fn (array $row): float => (float) $row['amount']));
        $orderLevelDiscountTotal = $this->money((float) $discountTotal - (float) $lineDiscount);
        $grandTotal = $this->money(
            (float) $linePayableTotal
            - (float) $orderLevelDiscountTotal
            + (float) $shippingTotal
            + (float) $extraTaxTotal
            + (float) $roundingAdjustment
        );
        $paid = $this->money($amountPaid);
        $changeAmount = $this->money(max(0, (float) $paid - (float) $grandTotal));

        $rows[] = [
            'code' => OrderTotal::CODE_GRAND_TOTAL,
            'title' => 'Grand Total',
            'amount' => $grandTotal,
            'sort_order' => 100,
            'source' => 'system',
            'metadata' => null,
        ];

        return [
            'summary' => [
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'shipping_total' => $shippingTotal,
                'tax_total' => $taxTotal,
                'rounding_adjustment' => $roundingAdjustment,
                'grand_total' => $grandTotal,
                'amount_paid' => $paid,
                'change_amount' => $changeAmount,
                '_line_payable_total' => $linePayableTotal,
                '_line_discount_total' => $lineDiscount,
                '_line_tax_total' => $lineTax,
            ],
            'rows' => collect($rows)->sortBy([['sort_order', 'asc']])->values()->all(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function save(Order $order, array $summary, array $rows): void
    {
        $this->assertGrandTotalMatches($summary);
        unset($summary['_line_payable_total'], $summary['_line_discount_total'], $summary['_line_tax_total']);

        $order->forceFill($summary)->save();
        $order->totals()->delete();

        foreach ($rows as $row) {
            $order->totals()->create($row);
        }
    }

    private function normalizeRow(array $row): array
    {
        $amount = $this->money($row['amount'] ?? 0);

        if ($this->isDiscountCode((string) ($row['code'] ?? '')) && (float) $amount > 0) {
            $amount = $this->money(-1 * (float) $amount);
        }

        return [
            'code' => (string) $row['code'],
            'title' => (string) ($row['title'] ?? $row['code']),
            'amount' => $amount,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'source' => $row['source'] ?? null,
            'metadata' => $row['metadata'] ?? null,
        ];
    }

    private function assertGrandTotalMatches(array $summary): void
    {
        if (array_key_exists('_line_payable_total', $summary)) {
            $orderLevelDiscountTotal = $this->money(
                (float) $summary['discount_total'] - (float) $summary['_line_discount_total']
            );
            $extraTaxTotal = $this->money(
                (float) $summary['tax_total'] - (float) $summary['_line_tax_total']
            );
            $expected = $this->money(
                (float) $summary['_line_payable_total']
                - (float) $orderLevelDiscountTotal
                + (float) $summary['shipping_total']
                + (float) $extraTaxTotal
                + (float) $summary['rounding_adjustment']
            );
        } else {
            $expected = $this->money(
                (float) $summary['subtotal']
                - (float) $summary['discount_total']
                + (float) $summary['shipping_total']
                + (float) $summary['tax_total']
                + (float) $summary['rounding_adjustment']
            );
        }

        if ($expected !== $this->money($summary['grand_total'])) {
            throw ValidationException::withMessages([
                'grand_total' => 'The order grand total does not match the summary totals.',
            ]);
        }
    }

    private function isDiscountCode(string $code): bool
    {
        return str_contains($code, 'discount') || str_contains($code, 'coupon');
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
