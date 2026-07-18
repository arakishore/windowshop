<?php

namespace App\Services\POS;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    /**
     * @param array{discount_type?: string|null, discount_value?: mixed} $discount
     */
    public function calculateLineDiscount(float|string|int $lineSubtotal, array $discount): array
    {
        return $this->calculateDiscount((float) $lineSubtotal, $discount, 'items');
    }

    /**
     * @param array{type?: string|null, value?: mixed, reason?: string|null, note?: string|null} $discount
     */
    public function calculateOrderDiscount(float|string|int $discountableSubtotal, array $discount): array
    {
        $calculated = $this->calculateDiscount((float) $discountableSubtotal, [
            'discount_type' => $discount['type'] ?? null,
            'discount_value' => $discount['value'] ?? null,
        ], 'order_discount');

        return [
            ...$calculated,
            'reason' => $this->nullableString($discount['reason'] ?? null),
            'note' => $this->nullableString($discount['note'] ?? null),
        ];
    }

    /**
     * @param array{discount_type?: string|null, discount_value?: mixed} $discount
     * @return array{type: string|null, value: string|null, amount: string}
     */
    private function calculateDiscount(float $baseAmount, array $discount, string $field): array
    {
        $type = $this->nullableString($discount['discount_type'] ?? null);
        $rawValue = $discount['discount_value'] ?? null;

        if ($type === null && ($rawValue === null || $rawValue === '')) {
            return ['type' => null, 'value' => null, 'amount' => '0.00'];
        }

        if (! in_array($type, [Order::DISCOUNT_TYPE_PERCENT, Order::DISCOUNT_TYPE_AMOUNT], true)) {
            throw ValidationException::withMessages([
                "{$field}.discount_type" => 'Choose Percent or Amount discount.',
            ]);
        }

        if ($rawValue === null || $rawValue === '' || ! is_numeric($rawValue)) {
            throw ValidationException::withMessages([
                "{$field}.discount_value" => 'Enter a discount value.',
            ]);
        }

        $value = round((float) $rawValue, 2);
        if ($value < 0) {
            throw ValidationException::withMessages([
                "{$field}.discount_value" => 'Discount cannot be negative.',
            ]);
        }

        if ($type === Order::DISCOUNT_TYPE_PERCENT) {
            if ($value > 100) {
                throw ValidationException::withMessages([
                    "{$field}.discount_value" => 'Discount percent cannot be more than 100.',
                ]);
            }

            $amount = round($baseAmount * ($value / 100), 2);
        } else {
            $amount = $value;
        }

        if ($amount > round($baseAmount, 2)) {
            throw ValidationException::withMessages([
                "{$field}.discount_value" => 'Discount cannot be more than the subtotal.',
            ]);
        }

        return [
            'type' => $type,
            'value' => $this->money($value),
            'amount' => $this->money($amount),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
