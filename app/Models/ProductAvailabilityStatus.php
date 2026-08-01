<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAvailabilityStatus extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const CODE_IN_STOCK = 'IN_STOCK';
    public const CODE_OUT_OF_STOCK = 'OUT_OF_STOCK';
    public const CODE_PREORDER = 'PREORDER';
    public const CODE_BACKORDER = 'BACKORDER';
    public const CODE_COMING_SOON = 'COMING_SOON';
    public const CODE_DISCONTINUED = 'DISCONTINUED';

    public const BADGE_SUCCESS = 'success';
    public const BADGE_DANGER = 'danger';
    public const BADGE_WARNING = 'warning';
    public const BADGE_SECONDARY = 'secondary';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'code',
        'name',
        'customer_description',
        'purchase_allowed',
        'badge_type',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_allowed' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'availability_status_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'availability_status_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('deleted_at');
    }

    public function isCoreDefault(): bool
    {
        return in_array($this->code, array_keys(self::defaults()), true);
    }

    public function safeBadgeClass(): string
    {
        return match ($this->badge_type) {
            self::BADGE_SUCCESS => 'bg-success',
            self::BADGE_DANGER => 'bg-danger',
            self::BADGE_WARNING => 'bg-warning',
            default => 'bg-secondary',
        };
    }

    /**
     * @return array<string, array{name: string, customer_description: string, purchase_allowed: bool, badge_type: string, sort_order: int}>
     */
    public static function defaults(): array
    {
        return [
            self::CODE_IN_STOCK => [
                'name' => 'In Stock',
                'customer_description' => 'This item is available and ready to order.',
                'purchase_allowed' => true,
                'badge_type' => self::BADGE_SUCCESS,
                'sort_order' => 10,
            ],
            self::CODE_OUT_OF_STOCK => [
                'name' => 'Out of Stock',
                'customer_description' => 'This item is currently out of stock.',
                'purchase_allowed' => false,
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 20,
            ],
            self::CODE_PREORDER => [
                'name' => 'Pre-Order',
                'customer_description' => 'Order now and we will fulfil it when it becomes available.',
                'purchase_allowed' => true,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 30,
            ],
            self::CODE_BACKORDER => [
                'name' => 'Backorder',
                'customer_description' => 'This item is not in stock right now, but you can order it for later fulfilment.',
                'purchase_allowed' => true,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 40,
            ],
            self::CODE_COMING_SOON => [
                'name' => 'Coming Soon',
                'customer_description' => 'This item is coming soon and is not available to order yet.',
                'purchase_allowed' => false,
                'badge_type' => self::BADGE_SECONDARY,
                'sort_order' => 50,
            ],
            self::CODE_DISCONTINUED => [
                'name' => 'Discontinued',
                'customer_description' => 'This item is no longer available for purchase.',
                'purchase_allowed' => false,
                'badge_type' => self::BADGE_SECONDARY,
                'sort_order' => 60,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function badgeTypes(): array
    {
        return [
            self::BADGE_SUCCESS,
            self::BADGE_DANGER,
            self::BADGE_WARNING,
            self::BADGE_SECONDARY,
        ];
    }
}
