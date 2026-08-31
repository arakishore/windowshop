<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionReward extends Model
{
    public const TYPE_PERCENTAGE_DISCOUNT = 'percentage_discount';
    public const TYPE_FIXED_DISCOUNT = 'fixed_discount';
    public const TYPE_FIXED_PRICE = 'fixed_price';
    public const TYPE_FIXED_BUNDLE_PRICE = 'fixed_bundle_price';
    public const TYPE_BUY_X_GET_Y_FREE = 'buy_x_get_y_free';
    public const TYPE_BUY_X_GET_Y_DISCOUNT = 'buy_x_get_y_discount';
    public const TYPE_QUANTITY_DISCOUNT = 'quantity_discount';
    public const TYPE_TIER_PRICING = 'tier_pricing';
    public const TYPE_FREE_GIFT = 'free_gift';

    protected $fillable = [
        'promotion_id',
        'reward_type',
        'value_type',
        'value_amount',
        'value_percent',
        'max_discount_amount',
        'buy_quantity',
        'get_quantity',
        'bundle_quantity',
        'bundle_price',
        'tier_config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value_amount' => 'decimal:2',
            'value_percent' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'buy_quantity' => 'integer',
            'get_quantity' => 'integer',
            'bundle_quantity' => 'integer',
            'bundle_price' => 'decimal:2',
            'tier_config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
