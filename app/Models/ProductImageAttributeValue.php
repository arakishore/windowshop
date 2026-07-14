<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImageAttributeValue extends Model
{
    protected $fillable = [
        'product_image_id',
        'product_attribute_group_value_id',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeGroupValue::class, 'product_attribute_group_value_id');
    }
}
