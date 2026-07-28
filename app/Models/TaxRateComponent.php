<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRateComponent extends Model
{
    use SoftDeletes;

    public const JURISDICTION_CENTRAL = 'central';
    public const JURISDICTION_STATE = 'state';
    public const JURISDICTION_INTEGRATED = 'integrated';
    public const JURISDICTION_CESS = 'cess';
    public const JURISDICTION_LOCAL = 'local';

    protected $fillable = [
        'tax_rate_id',
        'code',
        'name',
        'rate',
        'jurisdiction_type',
        'priority',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate_id' => 'integer',
            'rate' => 'decimal:4',
            'priority' => 'integer',
        ];
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }
}
