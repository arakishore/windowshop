<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopCategory extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
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

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function productDescriptionTemplates(): HasMany
    {
        return $this->hasMany(ProductDescriptionTemplate::class);
    }
}
