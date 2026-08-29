<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'item_discount_type',
        'item_discount_value',
        'line_discount',
        'line_tax',
        'line_total',
        'refund_allowed',
        'refund_window_days',
        'exchange_allowed',
        'exchange_window_days',
        'tax_enabled',
        'tax_resolution_source',
        'tax_class_id',
        'tax_class_code',
        'tax_class_name',
        'tax_rate_id',
        'tax_rate_name',
        'tax_rate',
        'price_mode',
        'taxable_amount',
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
            'item_discount_value' => 'decimal:2',
            'line_discount' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'refund_allowed' => 'boolean',
            'refund_window_days' => 'integer',
            'exchange_allowed' => 'boolean',
            'exchange_window_days' => 'integer',
            'tax_enabled' => 'boolean',
            'tax_class_id' => 'integer',
            'tax_rate_id' => 'integer',
            'tax_rate' => 'decimal:4',
            'taxable_amount' => 'decimal:2',
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

    public function taxComponents(): HasMany
    {
        return $this->hasMany(OrderItemTaxComponent::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
