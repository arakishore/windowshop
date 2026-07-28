<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        'sort_order',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'sort_order' => 'integer',
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

    public function displayLabel(): string
    {
        $label = "{$this->code} / {$this->name}";
        $rate = $this->displayRate();

        if (! $rate instanceof TaxRate) {
            return $label;
        }

        return "{$label} - {$rate->total_rate}%".$this->componentSummary($rate);
    }

    public function taxSummaryLabel(): string
    {
        $rate = $this->displayRate();

        if (! $rate instanceof TaxRate) {
            return $this->name;
        }

        return "{$this->name} ({$rate->total_rate}%".$this->componentSummary($rate).')';
    }

    private function displayRate(): ?TaxRate
    {
        $rates = $this->relationLoaded('rates')
            ? $this->rates
            : $this->rates()
                ->active()
                ->with('components')
                ->orderByDesc('effective_from')
                ->orderBy('priority')
                ->orderByDesc('id')
                ->get();

        return $rates
            ->filter(fn (TaxRate $rate): bool => $rate->status === TaxRate::STATUS_ACTIVE)
            ->sortByDesc(fn (TaxRate $rate): string => $rate->effective_from?->format('Y-m-d') ?? '')
            ->first();
    }

    private function componentSummary(TaxRate $rate): string
    {
        /** @var Collection<int, TaxRateComponent> $components */
        $components = $rate->relationLoaded('components')
            ? $rate->components
            : $rate->components()->get();

        if ($components->isEmpty()) {
            return '';
        }

        $summary = $components
            ->sortBy('priority')
            ->map(fn (TaxRateComponent $component): string => "{$component->code} {$component->rate}%")
            ->implode(' + ');

        return " ({$summary})";
    }
}
