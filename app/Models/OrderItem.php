<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_image',
        'variant_name',
        'sku',
        'barcode',
        'quantity',
        'unit_mrp',
        'unit_price',
        'unit_discount',
        'line_subtotal',
        'line_discount',
        'line_tax',
        'line_total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_mrp' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'unit_discount' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_discount' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
