<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const ACTIVATION_AUTOMATIC = 'automatic';
    public const ACTIVATION_COUPON = 'coupon';

    public const ORIGIN_SYSTEM = 'system';
    public const ORIGIN_MERCHANT = 'merchant';

    public const POLICY_INHERIT = 'inherit';
    public const POLICY_ALLOWED = 'allowed';
    public const POLICY_NOT_ALLOWED = 'not_allowed';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'shop_id',
        'promotion_template_id',
        'name',
        'slug',
        'description',
        'status',
        'activation_type',
        'origin',
        'starts_at',
        'ends_at',
        'is_combinable',
        'priority',
        'total_usage_limit',
        'per_customer_usage_limit',
        'new_customer_only',
        'refund_policy_mode',
        'refund_window_days',
        'exchange_policy_mode',
        'exchange_window_days',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_combinable' => 'boolean',
            'priority' => 'integer',
            'total_usage_limit' => 'integer',
            'per_customer_usage_limit' => 'integer',
            'new_customer_only' => 'boolean',
            'refund_window_days' => 'integer',
            'exchange_window_days' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromotionTemplate::class, 'promotion_template_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(PromotionReward::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(PromotionCoupon::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function scopeActiveNow(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now = $now ?: now();

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function scopeScheduled(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', $now ?: now());
    }

    public function scopeExpired(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now ?: now());
    }

    public function lifecycleLabel(?CarbonInterface $now = null): string
    {
        $now = $now ?: now();

        if ($this->status !== self::STATUS_ACTIVE) {
            return ucfirst($this->status);
        }

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return 'Scheduled';
        }

        if ($this->ends_at && $this->ends_at->lt($now)) {
            return 'Expired';
        }

        return 'Active';
    }

    public function isSystemStarter(): bool
    {
        return $this->origin === self::ORIGIN_SYSTEM;
    }

    public function setupStatusLabel(): string
    {
        if ($this->status !== self::STATUS_ACTIVE && ! $this->isSetupComplete()) {
            return 'Needs Setup';
        }

        return $this->lifecycleLabel();
    }

    public function setupStatusBadgeClass(): string
    {
        if ($this->status !== self::STATUS_ACTIVE && ! $this->isSetupComplete()) {
            return 'bg-info';
        }

        return match ($this->status) {
            self::STATUS_ACTIVE => 'bg-success',
            self::STATUS_INACTIVE => 'bg-warning',
            default => 'bg-light text-body border',
        };
    }

    public function isSetupComplete(): bool
    {
        return $this->setupIssues() === [];
    }

    public function setupIssues(): array
    {
        $rewardType = $this->template?->reward_type ?: $this->firstReward()?->reward_type;
        $reward = $this->firstReward();

        if (! $rewardType || ! $reward) {
            return ['Configure the offer reward before activating this offer.'];
        }

        return match ($rewardType) {
            PromotionReward::TYPE_PERCENTAGE_DISCOUNT => [
                ...$this->requirePositive($reward->value_percent, 'Add a discount percentage before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_ELIGIBLE, 'Choose eligible products before activating this offer.'),
            ],
            PromotionReward::TYPE_FIXED_DISCOUNT => [
                ...$this->requirePositive($reward->value_amount, 'Add a discount amount before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_ELIGIBLE, 'Choose eligible products before activating this offer.'),
            ],
            PromotionReward::TYPE_FIXED_PRICE => [
                ...$this->requirePositive($reward->value_amount, 'Add a promotional fixed price before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_ELIGIBLE, 'Choose eligible products before activating this offer.'),
            ],
            PromotionReward::TYPE_FIXED_BUNDLE_PRICE => [
                ...$this->requirePositive($reward->bundle_quantity, 'Add a bundle quantity before activating this offer.'),
                ...$this->requirePositive($reward->bundle_price, 'Add a bundle price before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_ELIGIBLE, 'Choose eligible products before activating this offer.'),
            ],
            PromotionReward::TYPE_BUY_X_GET_Y_FREE => [
                ...$this->requirePositive($reward->buy_quantity, 'Add the buy quantity before activating this offer.'),
                ...$this->requirePositive($reward->get_quantity, 'Add the get quantity before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_BUY, 'Choose buy targets before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_GET, 'Choose get targets before activating this offer.'),
            ],
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => [
                ...$this->requirePositive($reward->buy_quantity, 'Add the buy quantity before activating this offer.'),
                ...$this->requirePositive($reward->get_quantity, 'Add the get quantity before activating this offer.'),
                ...$this->requirePositive($reward->value_percent, 'Add the get discount percentage before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_BUY, 'Choose buy targets before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_GET, 'Choose get targets before activating this offer.'),
            ],
            PromotionReward::TYPE_QUANTITY_DISCOUNT => [
                ...$this->requireCondition(PromotionCondition::TYPE_MINIMUM_QUANTITY, 'Add the minimum quantity before activating this offer.'),
                ...($reward->value_type === 'amount'
                    ? $this->requirePositive($reward->value_amount, 'Add a discount amount before activating this offer.')
                    : $this->requirePositive($reward->value_percent, 'Add a discount percentage before activating this offer.')),
                ...$this->requireTargetRole(PromotionTarget::ROLE_ELIGIBLE, 'Choose eligible products before activating this offer.'),
            ],
            PromotionReward::TYPE_TIER_PRICING => $this->validTierConfig($reward->tier_config)
                ? $this->requireTargetRole(PromotionTarget::ROLE_ELIGIBLE, 'Choose eligible products before activating this offer.')
                : ['Add tier pricing rows before activating this offer.'],
            PromotionReward::TYPE_FREE_GIFT => [
                ...$this->requireCondition(PromotionCondition::TYPE_MINIMUM_ELIGIBLE_SUBTOTAL, 'Add the purchase subtotal before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_ELIGIBLE, 'Choose eligible products before activating this offer.'),
                ...$this->requireTargetRole(PromotionTarget::ROLE_GIFT, 'Select a gift product before activating this offer.'),
            ],
            default => ['Unsupported promotion template.'],
        };
    }

    public function refundAllowedOverride(): ?bool
    {
        return $this->policyOverride($this->refund_policy_mode);
    }

    public function exchangeAllowedOverride(): ?bool
    {
        return $this->policyOverride($this->exchange_policy_mode);
    }

    private function policyOverride(string $mode): ?bool
    {
        return match ($mode) {
            self::POLICY_ALLOWED => true,
            self::POLICY_NOT_ALLOWED => false,
            default => null,
        };
    }

    private function firstReward(): ?PromotionReward
    {
        return $this->relationLoaded('rewards')
            ? $this->rewards->first()
            : $this->rewards()->first();
    }

    private function requirePositive(mixed $value, string $message): array
    {
        return (float) ($value ?? 0) > 0 ? [] : [$message];
    }

    private function requireTargetRole(string $role, string $message): array
    {
        $exists = $this->relationLoaded('targets')
            ? $this->targets->contains(fn (PromotionTarget $target): bool => $target->target_role === $role)
            : $this->targets()->where('target_role', $role)->exists();

        return $exists ? [] : [$message];
    }

    private function requireCondition(string $type, string $message): array
    {
        $exists = $this->relationLoaded('conditions')
            ? $this->conditions->contains(fn (PromotionCondition $condition): bool => $condition->condition_type === $type && (float) ($condition->value_numeric ?? 0) > 0)
            : $this->conditions()
                ->where('condition_type', $type)
                ->where('value_numeric', '>', 0)
                ->exists();

        return $exists ? [] : [$message];
    }

    private function validTierConfig(?array $tiers): bool
    {
        if (! is_array($tiers) || $tiers === []) {
            return false;
        }

        foreach ($tiers as $tier) {
            if ((int) ($tier['min_quantity'] ?? 0) > 0 && (float) ($tier['unit_price'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }
}
