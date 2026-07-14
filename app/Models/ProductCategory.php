<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'root_product_category_id');
    }

    public function rootProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'root_product_category_id');
    }

    public function descriptionTemplates(): HasMany
    {
        return $this->hasMany(ProductDescriptionTemplate::class);
    }

    public function attributeGroupMappings(): HasMany
    {
        return $this->hasMany(ProductCategoryAttributeGroup::class, 'root_product_category_id');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(
            Brand::class,
            'brand_root_product_categories',
            'root_product_category_id',
            'brand_id',
        )->withTimestamps();
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

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }

        return ! $this->children()->exists();
    }

    public function isDescendantOf(self $root): bool
    {
        $visited = [];
        $current = $this;

        while ($current->parent_id !== null && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();

            if ((int) $current->parent_id === (int) $root->getKey()) {
                return true;
            }

            $current = $current->parent;

            if (! $current) {
                break;
            }
        }

        return false;
    }

    public function rootCategoryId(): int
    {
        $visited = [];
        $current = $this;

        while ($current->parent_id !== null && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();
            $parent = $current->parent;

            if (! $parent) {
                break;
            }

            $current = $parent;
        }

        return (int) $current->getKey();
    }
}
