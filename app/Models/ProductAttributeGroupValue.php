<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttributeGroupValue extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'product_attribute_group_id',
        'name',
        'code',
        'description',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeGroup::class, 'product_attribute_group_id');
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class, 'product_attribute_group_value_id');
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'product_attribute_group_value_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
