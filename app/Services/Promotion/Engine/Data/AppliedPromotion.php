<?php

namespace App\Services\Promotion\Engine\Data;

class AppliedPromotion
{
    public function __construct(
        public readonly int $promotionId,
        public readonly string $promotionName,
        public readonly ?string $promotionSlug,
        public readonly string $templateCode,
        public readonly string $rewardType,
        public readonly int $priority,
        public readonly int $discountCents,
        public readonly array $details = [],
        public readonly string $activationType = 'automatic',
        public readonly ?int $couponId = null,
        public readonly ?string $couponCode = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        return [
            'id' => $this->promotionId,
            'name' => $this->promotionName,
            'slug' => $this->promotionSlug,
            'template_code' => $this->templateCode,
            'reward_type' => $this->rewardType,
            'priority' => $this->priority,
            'discount_amount' => $this->moneyFromCents($this->discountCents),
            'details' => $this->details,
            'activation_type' => $this->activationType,
            'coupon_id' => $this->couponId,
            'coupon_code' => $this->couponCode,
        ];
    }

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
