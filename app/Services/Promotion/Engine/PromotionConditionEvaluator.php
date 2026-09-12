<?php

namespace App\Services\Promotion\Engine;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Services\Promotion\Engine\Data\PromotionLineInput;

class PromotionConditionEvaluator
{
    public function passes(Promotion $promotion, PromotionLineInput $line, ?Customer $customer = null, bool $allowGuestCustomerPreview = false): bool
    {
        if ($promotion->new_customer_only && ! ($allowGuestCustomerPreview && ! $customer instanceof Customer) && ! $this->isNewCustomerForShop($line->shopId, $customer)) {
            return false;
        }

        foreach ($promotion->conditions as $condition) {
            if (! $this->passesCondition($condition, $line)) {
                return false;
            }
        }

        return true;
    }

    private function passesCondition(PromotionCondition $condition, PromotionLineInput $line): bool
    {
        return match ($condition->condition_type) {
            PromotionCondition::TYPE_MINIMUM_QUANTITY => $this->compareDecimal(
                $line->quantity,
                (string) $condition->value_numeric,
                $condition->operator ?: '>=',
            ),
            PromotionCondition::TYPE_MINIMUM_ELIGIBLE_SUBTOTAL => $this->compareDecimal(
                $this->moneyFromCents($this->lineSubtotalCents($line)),
                (string) $condition->value_numeric,
                $condition->operator ?: '>=',
            ),
            default => true,
        };
    }

    private function isNewCustomerForShop(int $shopId, ?Customer $customer): bool
    {
        if (! $customer instanceof Customer) {
            return false;
        }

        return ! Order::query()
            ->where('shop_id', $shopId)
            ->where('customer_id', $customer->getKey())
            ->where('order_status', Order::STATUS_COMPLETED)
            ->exists();
    }

    private function compareDecimal(string $left, string $right, string $operator): bool
    {
        $leftValue = (float) $left;
        $rightValue = (float) $right;

        return match ($operator) {
            '>' => $leftValue > $rightValue,
            '=' , '==' => $leftValue === $rightValue,
            '<' => $leftValue < $rightValue,
            '<=' => $leftValue <= $rightValue,
            default => $leftValue >= $rightValue,
        };
    }

    private function lineSubtotalCents(PromotionLineInput $line): int
    {
        return intdiv(($this->quantityToUnits($line->quantity) * $this->moneyToCents($line->baseUnitPrice)) + 500, 1000);
    }

    private function quantityToUnits(string $quantity): int
    {
        return (int) round(((float) $quantity) * 1000);
    }

    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
