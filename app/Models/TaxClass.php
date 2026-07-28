<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxClass extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'uuid',
        'country_id',
        'code',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(LocCountry::class, 'country_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function activeRates(): HasMany
    {
        return $this->rates()->active();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
