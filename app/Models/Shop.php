<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'root_product_category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'logo_path',
        'banner_path',
        'email',
        'mobile',
        'whatsapp_number',
        'website_url',
        'address_line_1',
        'address_line_2',
        'landmark',
        'country_id',
        'state_id',
        'city_id',
        'pincode',
        'latitude',
        'longitude',
        'status',
        'admin_note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function rootProductCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'root_product_category_id');
    }

    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(ShopAudience::class, 'shop_audience_map', 'shop_id', 'audience_id')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(LocCountry::class, 'country_id')->withTrashed();
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(LocState::class, 'state_id')->withTrashed();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(LocCity::class, 'city_id')->withTrashed();
    }
}
