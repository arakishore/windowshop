<?php

namespace App\Services\Promotion\Coupons;

use App\Models\PromotionCoupon;

class CouponResolution
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?PromotionCoupon $coupon = null,
        public readonly ?string $code = null,
        public readonly bool $clearStoredState = false,
    ) {
    }

    public function valid(): bool
    {
        return $this->coupon instanceof PromotionCoupon;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'code' => $this->code ?? $this->coupon?->code,
        ];
    }
}
