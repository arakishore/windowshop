<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimonial extends Model
{
    use HasUuid, SoftDeletes;

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_MERCHANT = 'merchant';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'uuid',
        'type',
        'name',
        'photo_path',
        'body',
        'location',
        'rating',
        'business_name',
        'designation',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
