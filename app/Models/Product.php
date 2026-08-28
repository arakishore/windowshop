<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'merchant_id',
        'shop_id',
        'root_product_category_id',
        'product_category_id',
        'brand_id',
        'availability_status_id',
        'tax_mode',
        'tax_class_id',
        'primary_image_id',
        'product_name',
        'slug',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_featured',
        'featured_from',
        'featured_until',
        'status',
        'published_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'featured_from' => 'datetime',
            'featured_until' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function slugFromName(): string
    {
        return (Str::slug($this->product_name) ?: 'product').'-'.$this->getKey();
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeCurrentlyFeatured(Builder $query, ?CarbonInterface $effectiveAt = null): Builder
    {
        $effectiveAt = $effectiveAt ?: now();

        return $query
            ->featured()
            ->where(function (Builder $query) use ($effectiveAt): void {
                $query->whereNull('featured_from')
                    ->orWhere('featured_from', '<=', $effectiveAt);
            })
            ->where(function (Builder $query) use ($effectiveAt): void {
                $query->whereNull('featured_until')
                    ->orWhere('featured_until', '>=', $effectiveAt);
            });
    }

    public function scopeScheduledFeatured(Builder $query, ?CarbonInterface $effectiveAt = null): Builder
    {
        $effectiveAt = $effectiveAt ?: now();

        return $query
            ->featured()
            ->whereNotNull('featured_from')
            ->where('featured_from', '>', $effectiveAt);
    }

    public function scopeExpiredFeatured(Builder $query, ?CarbonInterface $effectiveAt = null): Builder
    {
        $effectiveAt = $effectiveAt ?: now();

        return $query
            ->featured()
            ->whereNotNull('featured_until')
            ->where('featured_until', '<', $effectiveAt);
    }

    public function scopeFeaturedOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('product_name')
            ->orderBy('id');
    }

    public function isCurrentlyFeatured(?CarbonInterface $effectiveAt = null): bool
    {
        $effectiveAt = $effectiveAt ?: now();

        if (! $this->is_featured) {
            return false;
        }

        if ($this->featured_from && $this->featured_from->gt($effectiveAt)) {
            return false;
        }

        if ($this->featured_until && $this->featured_until->lt($effectiveAt)) {
            return false;
        }

        return true;
    }

    public function featuredStatus(?CarbonInterface $effectiveAt = null): string
    {
        $effectiveAt = $effectiveAt ?: now();

        if (! $this->is_featured) {
            return 'disabled';
        }

        if ($this->featured_from && $this->featured_from->gt($effectiveAt)) {
            return 'scheduled';
        }

        if ($this->featured_until && $this->featured_until->lt($effectiveAt)) {
            return 'expired';
        }

        return 'current';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function rootProductCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'root_product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function availabilityStatus(): BelongsTo
    {
        return $this->belongsTo(ProductAvailabilityStatus::class, 'availability_status_id')->withTrashed();
    }

    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'primary_image_id');
    }

    public function storefrontCardVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'storefront_variant_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function returnPolicy(): HasOne
    {
        return $this->hasOne(ProductReturnPolicy::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }
}
