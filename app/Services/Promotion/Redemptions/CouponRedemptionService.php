<?php

namespace App\Services\Promotion\Redemptions;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionCoupon;
use App\Models\PromotionRedemption;
use App\Models\Shop;
use App\Services\Promotion\Engine\Data\PromotionCalculationResult;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

class CouponRedemptionService
{
    public function lockAvailableCouponForCheckout(
        Shop $shop,
        PromotionCoupon $coupon,
        ?Customer $customer,
        CarbonInterface $effectiveAt,
    ): ?PromotionCoupon {
        $promotion = Promotion::query()
            ->with(['template', 'rewards', 'targets', 'conditions'])
            ->whereKey($coupon->promotion_id)
            ->lockForUpdate()
            ->first();

        $lockedCoupon = PromotionCoupon::query()
            ->whereKey($coupon->getKey())
            ->lockForUpdate()
            ->first();

        if (! $promotion instanceof Promotion || ! $lockedCoupon instanceof PromotionCoupon) {
            return null;
        }

        $lockedCoupon->setRelation('promotion', $promotion);

        if (! $this->isCheckoutEligible($shop, $promotion, $lockedCoupon, $effectiveAt)) {
            return null;
        }

        if ($this->limitReached($promotion->total_usage_limit, $this->redeemedCountForPromotion((int) $promotion->getKey()))) {
            return null;
        }

        if ($customer instanceof Customer
            && $this->limitReached($promotion->per_customer_usage_limit, $this->redeemedCountForPromotion((int) $promotion->getKey(), $customer))) {
            return null;
        }

        if ($this->limitReached($lockedCoupon->usage_limit, $this->redeemedCountForCoupon((int) $lockedCoupon->getKey()))) {
            return null;
        }

        if ($customer instanceof Customer
            && $this->limitReached($lockedCoupon->per_customer_usage_limit, $this->redeemedCountForCoupon((int) $lockedCoupon->getKey(), $customer))) {
            return null;
        }

        return $lockedCoupon;
    }

    public function redeemWinningCoupon(Order $order, PromotionCalculationResult $result): ?PromotionRedemption
    {
        $summary = $this->winningCouponSummary($result);
        if ($summary === null) {
            return null;
        }

        $existing = PromotionRedemption::query()
            ->where('order_id', $order->getKey())
            ->where('promotion_id', $summary['promotion_id'])
            ->where('promotion_coupon_id', $summary['coupon_id'])
            ->first();

        if ($existing instanceof PromotionRedemption) {
            return $existing;
        }

        try {
            return PromotionRedemption::query()->create([
                'promotion_id' => $summary['promotion_id'],
                'promotion_coupon_id' => $summary['coupon_id'],
                'order_id' => $order->getKey(),
                'customer_id' => $order->customer_id,
                'shop_id' => $order->shop_id,
                'discount_amount' => $this->moneyFromCents($summary['discount_cents']),
                'status' => PromotionRedemption::STATUS_REDEEMED,
                'redeemed_at' => now(),
                'metadata' => [
                    'coupon_code' => $summary['coupon_code'],
                    'source' => $order->created_source,
                    'discount_source' => 'winning_order_item_allocations',
                ],
            ]);
        } catch (QueryException $exception) {
            $existing = PromotionRedemption::query()
                ->where('order_id', $order->getKey())
                ->where('promotion_id', $summary['promotion_id'])
                ->where('promotion_coupon_id', $summary['coupon_id'])
                ->first();

            if (! $existing instanceof PromotionRedemption || (string) ($exception->errorInfo[0] ?? '') !== '23000') {
                throw $exception;
            }

            return $existing;
        }
    }

    public function cancelForOrder(Order $order): int
    {
        return PromotionRedemption::query()
            ->where('order_id', $order->getKey())
            ->where('status', PromotionRedemption::STATUS_REDEEMED)
            ->update([
                'status' => PromotionRedemption::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function isCheckoutEligible(Shop $shop, Promotion $promotion, PromotionCoupon $coupon, CarbonInterface $effectiveAt): bool
    {
        return (int) $promotion->shop_id === (int) $shop->getKey()
            && (int) $coupon->shop_id === (int) $shop->getKey()
            && (int) $coupon->promotion_id === (int) $promotion->getKey()
            && $promotion->activation_type === Promotion::ACTIVATION_COUPON
            && $promotion->status === Promotion::STATUS_ACTIVE
            && $coupon->status === PromotionCoupon::STATUS_ACTIVE
            && (! $promotion->starts_at || $promotion->starts_at->lte($effectiveAt))
            && (! $promotion->ends_at || $promotion->ends_at->gte($effectiveAt))
            && (! $coupon->starts_at || $coupon->starts_at->lte($effectiveAt))
            && (! $coupon->ends_at || $coupon->ends_at->gte($effectiveAt))
            && $promotion->isSetupComplete();
    }

    private function redeemedCountForPromotion(int $promotionId, ?Customer $customer = null): int
    {
        return PromotionRedemption::query()
            ->where('promotion_id', $promotionId)
            ->where('status', PromotionRedemption::STATUS_REDEEMED)
            ->when($customer instanceof Customer, fn ($query) => $query->where('customer_id', $customer->getKey()))
            ->count();
    }

    private function redeemedCountForCoupon(int $couponId, ?Customer $customer = null): int
    {
        return PromotionRedemption::query()
            ->where('promotion_coupon_id', $couponId)
            ->where('status', PromotionRedemption::STATUS_REDEEMED)
            ->when($customer instanceof Customer, fn ($query) => $query->where('customer_id', $customer->getKey()))
            ->count();
    }

    private function limitReached(?int $limit, int $redeemed): bool
    {
        return $limit !== null && $limit > 0 && $redeemed >= $limit;
    }

    /**
     * @return array{promotion_id: int, coupon_id: int, coupon_code: string|null, discount_cents: int}|null
     */
    private function winningCouponSummary(PromotionCalculationResult $result): ?array
    {
        $summary = null;

        foreach ($result->lineAdjustments as $adjustment) {
            $winner = $adjustment->winningPromotion;
            if ($winner === null || $winner->activationType !== Promotion::ACTIVATION_COUPON || $winner->couponId === null) {
                continue;
            }

            $key = $winner->promotionId.':'.$winner->couponId;
            if ($summary !== null && $summary['key'] !== $key) {
                continue;
            }

            $summary ??= [
                'key' => $key,
                'promotion_id' => $winner->promotionId,
                'coupon_id' => $winner->couponId,
                'coupon_code' => $winner->couponCode,
                'discount_cents' => 0,
            ];
            $summary['discount_cents'] += max(0, $adjustment->promotionDiscountCents);
        }

        if ($summary === null || $summary['discount_cents'] <= 0) {
            return null;
        }

        unset($summary['key']);

        return $summary;
    }

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
