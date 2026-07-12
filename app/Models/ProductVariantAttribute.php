<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantAttribute extends Model
{
    protected $fillable = [
        'product_variant_id',
        'product_attribute_group_id',
        'product_attribute_group_value_id',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeGroup::class, 'product_attribute_group_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeGroupValue::class, 'product_attribute_group_value_id');
    }

    public function valueBelongsToGroup(): bool
    {
        return ProductAttributeGroupValue::query()
            ->whereKey($this->product_attribute_group_value_id)
            ->where('product_attribute_group_id', $this->product_attribute_group_id)
            ->exists();
    }
}
