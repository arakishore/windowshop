<?php

namespace App\Services\Promotion\Coupons;

use App\Models\Promotion;
use App\Models\PromotionCoupon;
use App\Models\PromotionReward;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class CouponResolver
{
    public const STATUS_APPLIED = 'applied';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_UNSUPPORTED_REWARD_TYPE = 'unsupported_reward_type';

    private const SUPPORTED_REWARD_TYPES = [
        PromotionReward::TYPE_PERCENTAGE_DISCOUNT,
        PromotionReward::TYPE_FIXED_DISCOUNT,
        PromotionReward::TYPE_FIXED_PRICE,
    ];

    public function normalize(mixed $code): string
    {
        return Str::upper(trim((string) $code));
    }

    public function resolveForShop(Shop|int $shop, mixed $code, ?CarbonInterface $effectiveAt = null): CouponResolution
    {
        $shopId = $shop instanceof Shop ? (int) $shop->getKey() : (int) $shop;
        $normalized = $this->normalize($code);
        $effectiveAt ??= now();

        if ($shopId < 1 || $normalized === '') {
            return new CouponResolution(
                self::STATUS_INVALID,
                'Enter a valid coupon code.',
                code: $normalized,
                clearStoredState: true,
            );
        }

        $coupon = PromotionCoupon::query()
            ->with(['promotion.template', 'promotion.rewards', 'promotion.targets', 'promotion.conditions'])
            ->where('shop_id', $shopId)
            ->where('code', $normalized)
            ->first();

        if (! $coupon instanceof PromotionCoupon) {
            return new CouponResolution(
                self::STATUS_INVALID,
                'This coupon is not valid for this shop.',
                code: $normalized,
                clearStoredState: true,
            );
        }

        if ($coupon->status !== PromotionCoupon::STATUS_ACTIVE) {
            return new CouponResolution(self::STATUS_INACTIVE, 'This coupon is currently unavailable.', code: $normalized, clearStoredState: true);
        }

        if ($coupon->starts_at && $coupon->starts_at->gt($effectiveAt)) {
            return new CouponResolution(self::STATUS_NOT_STARTED, 'This coupon is not active yet.', code: $normalized);
        }

        if ($coupon->ends_at && $coupon->ends_at->lt($effectiveAt)) {
            return new CouponResolution(self::STATUS_EXPIRED, 'This coupon has expired.', code: $normalized, clearStoredState: true);
        }

        $promotion = $coupon->promotion;
        if (! $promotion instanceof Promotion || (int) $promotion->shop_id !== $shopId || $promotion->activation_type !== Promotion::ACTIVATION_COUPON) {
            return new CouponResolution(self::STATUS_INVALID, 'This coupon is currently unavailable.', code: $normalized, clearStoredState: true);
        }

        if ($promotion->status !== Promotion::STATUS_ACTIVE) {
            return new CouponResolution(self::STATUS_INACTIVE, 'This coupon is currently unavailable.', code: $normalized, clearStoredState: true);
        }

        if ($promotion->starts_at && $promotion->starts_at->gt($effectiveAt)) {
            return new CouponResolution(self::STATUS_NOT_STARTED, 'This coupon is not active yet.', code: $normalized);
        }

        if ($promotion->ends_at && $promotion->ends_at->lt($effectiveAt)) {
            return new CouponResolution(self::STATUS_EXPIRED, 'This coupon has expired.', code: $normalized, clearStoredState: true);
        }

        if (! $promotion->isSetupComplete()) {
            return new CouponResolution(self::STATUS_INACTIVE, 'This coupon is currently unavailable.', code: $normalized, clearStoredState: true);
        }

        $rewardType = (string) $promotion->rewards->first()?->reward_type;
        if (! in_array($rewardType, self::SUPPORTED_REWARD_TYPES, true)) {
            return new CouponResolution(
                self::STATUS_UNSUPPORTED_REWARD_TYPE,
                'This coupon is not available for this offer yet.',
                code: $normalized,
            );
        }

        return new CouponResolution(self::STATUS_APPLIED, 'Coupon applied.', $coupon, $normalized);
    }
}
