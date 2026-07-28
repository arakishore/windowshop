<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantTaxSetting extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'merchant_id',
        'tax_enabled',
        'default_tax_class_id',
        'prices_include_tax',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'tax_enabled' => 'boolean',
            'default_tax_class_id' => 'integer',
            'prices_include_tax' => 'boolean',
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

    public function defaultTaxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class, 'default_tax_class_id');
    }
}
