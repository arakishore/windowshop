<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostalCode extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'source_key',
        'circle_name',
        'region_name',
        'division_name',
        'office_name',
        'postal_code',
        'office_type',
        'delivery_status',
        'shipping_enabled',
        'district',
        'state',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'shipping_enabled' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('deleted_at');
    }

    public function scopeShippingEnabled(Builder $query): Builder
    {
        return $query->where('shipping_enabled', true);
    }
}
