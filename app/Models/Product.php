<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'merchant_id',
        'shop_id',
        'product_category_id',
        'brand_id',
        'product_name',
        'slug',
        'product_type',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
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

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->shop_id === null) {
                return;
            }

            $product->shop_category_id = Shop::query()
                ->whereKey($product->shop_id)
                ->value('shop_category_id');
        });
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

    public function shopCategory(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'shop_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
