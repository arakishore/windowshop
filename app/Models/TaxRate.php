<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'uuid',
        'tax_class_id',
        'name',
        'total_rate',
        'effective_from',
        'effective_to',
        'priority',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'tax_class_id' => 'integer',
            'total_rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'priority' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(TaxRateComponent::class)
            ->orderBy('priority')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string|null $date = null): Builder
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : ($date ?? now()->toDateString());

        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->effectiveOn();
    }
}
