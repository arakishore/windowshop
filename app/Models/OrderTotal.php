<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTotal extends Model
{
    public const CODE_SUBTOTAL = 'subtotal';
    public const CODE_PRODUCT_DISCOUNT = 'product_discount';
    public const CODE_ITEM_DISCOUNT = 'item_discount';
    public const CODE_ORDER_DISCOUNT = 'order_discount';
    public const CODE_OFFER_DISCOUNT = 'offer_discount';
    public const CODE_COUPON_DISCOUNT = 'coupon_discount';
    public const CODE_SHIPPING = 'shipping';
    public const CODE_TAX = 'tax';
    public const CODE_CGST = 'cgst';
    public const CODE_SGST = 'sgst';
    public const CODE_IGST = 'igst';
    public const CODE_PACKAGING_FEE = 'packaging_fee';
    public const CODE_PLATFORM_FEE = 'platform_fee';
    public const CODE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const CODE_ROUNDING = 'rounding';
    public const CODE_GRAND_TOTAL = 'grand_total';

    protected $fillable = [
        'order_id',
        'code',
        'title',
        'amount',
        'sort_order',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
