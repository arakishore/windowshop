<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }

    public function descriptionTemplates(): HasMany
    {
        return $this->hasMany(ProductDescriptionTemplate::class);
    }

    public function getFullPathAttribute(): string
    {
        $names = [];
        $visited = [];
        $category = $this;

        while ($category && ! in_array($category->getKey(), $visited, true)) {
            $visited[] = $category->getKey();
            array_unshift($names, $category->name);
            $category = $category->parent;
        }

        return implode(' > ', $names);
    }
}
