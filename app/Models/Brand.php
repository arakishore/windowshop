<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'logo_path',
        'website_url',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function products(): HasMany
    {
        return $this->hasMany('App\Models\Product');
    }

    public function rootProductCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'brand_root_product_categories',
            'brand_id',
            'root_product_category_id',
        )->withTimestamps();
    }
}
