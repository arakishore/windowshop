<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRedemption extends Model
{
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'promotion_id',
        'promotion_coupon_id',
        'order_id',
        'customer_id',
        'shop_id',
        'discount_amount',
        'status',
        'redeemed_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'redeemed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(PromotionCoupon::class, 'promotion_coupon_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
