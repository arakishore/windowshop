<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionTemplate extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code',
        'name',
        'description',
        'example',
        'help_text',
        'reward_type',
        'required_fields',
        'configurable_fields',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'required_fields' => 'array',
            'configurable_fields' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
