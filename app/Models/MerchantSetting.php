<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantSetting extends Model
{
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_JSON = 'json';
    public const TYPE_STRING = 'string';

    public const TYPE_BOOL = self::TYPE_BOOLEAN;
    public const TYPE_FLOAT = self::TYPE_DECIMAL;
    public const TYPE_INT = self::TYPE_INTEGER;

    protected $fillable = [
        'merchant_id',
        'group',
        'setting_key',
        'setting_value',
        'setting_type',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }
}
