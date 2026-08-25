<?php

namespace App\Models;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Enums\BannerSourceType;
use App\Models\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const SOURCE_TEMPLATE = 'template';

    public const SOURCE_CUSTOM_UPLOAD = 'custom_upload';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'shop_id',
        'source_type',
        'banner_template_id',
        'position',
        'title',
        'subtitle',
        'description',
        'desktop_image_path',
        'mobile_image_path',
        'link_type',
        'link_value',
        'open_in_new_tab',
        'button_text',
        'sort_order',
        'starts_at',
        'ends_at',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'position' => BannerPosition::class,
            'source_type' => BannerSourceType::class,
            'link_type' => BannerLinkType::class,
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id')->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }
    /**
     * Keep historical template information even if
     * the template has been soft deleted.
     *
     * Live banners should never lose their template context.
     */
    public function bannerTemplate(): BelongsTo
    {
        return $this->belongsTo(BannerTemplate::class, 'banner_template_id')->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->bannerTemplate();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCurrentlyVisible(Builder $query, ?CarbonInterface $effectiveAt = null): Builder
    {
        $effectiveAt = $effectiveAt ?: now();

        return $query
            ->active()
            ->where(function (Builder $query) use ($effectiveAt): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $effectiveAt);
            })
            ->where(function (Builder $query) use ($effectiveAt): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $effectiveAt);
            });
    }

    public function scopeForMarketplace(Builder $query): Builder
    {
        return $query->whereNull('merchant_id')->whereNull('shop_id');
    }

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForPosition(Builder $query, BannerPosition|string $position): Builder
    {
        $value = $position instanceof BannerPosition ? $position->value : $position;

        return $query->where('position', $value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isActiveOrScheduled(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }

    public function usesTemplate(): bool
    {
        $sourceType = $this->source_type instanceof BannerSourceType ? $this->source_type->value : $this->source_type;

        return $sourceType === self::SOURCE_TEMPLATE;
    }

    public function usesCustomUpload(): bool
    {
        $sourceType = $this->source_type instanceof BannerSourceType ? $this->source_type->value : $this->source_type;

        return $sourceType === null || $sourceType === self::SOURCE_CUSTOM_UPLOAD;
    }
}
