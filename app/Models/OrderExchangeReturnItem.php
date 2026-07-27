<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderExchangeReturnItem extends Model
{
    protected $fillable = [
        'order_exchange_id',
        'order_item_id',
        'product_variant_id',
        'quantity',
        'unit_return_value',
        'line_tax',
        'line_total',
        'restocked',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_return_value' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'restocked' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(OrderExchange::class, 'order_exchange_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
