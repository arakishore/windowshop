<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDescriptionTemplate extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'product_category_id',
        'name',
        'short_description_template',
        'description_template',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id')->withTrashed();
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
