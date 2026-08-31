<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'shop_id',
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function slugBase(): string
    {
        return Str::slug($this->name) ?: 'collection';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id')->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_products')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('collection_products.sort_order')
            ->orderBy('products.product_name')
            ->orderBy('products.id');
    }

    public function promotionTargets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class, 'target_id')
            ->where('target_type', PromotionTarget::TYPE_COLLECTION);
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

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }
}
