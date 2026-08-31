<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCondition extends Model
{
    public const TYPE_MINIMUM_QUANTITY = 'minimum_quantity';
    public const TYPE_MINIMUM_ELIGIBLE_SUBTOTAL = 'minimum_eligible_subtotal';

    protected $fillable = [
        'promotion_id',
        'condition_type',
        'operator',
        'value_numeric',
        'value_text',
        'value_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'decimal:2',
            'value_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
