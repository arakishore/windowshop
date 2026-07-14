<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCategoryAttributeGroup extends Model
{
    protected $fillable = [
        'root_product_category_id',
        'product_attribute_group_id',
        'is_required',
        'is_variant',
        'is_image_attribute',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_variant' => 'boolean',
            'is_image_attribute' => 'boolean',
        ];
    }

    public function rootCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'root_product_category_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeGroup::class, 'product_attribute_group_id');
    }
}
