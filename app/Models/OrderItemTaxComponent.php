<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemTaxComponent extends Model
{
    protected $fillable = [
        'order_item_id',
        'tax_component_id',
        'component_code',
        'component_name',
        'jurisdiction_type',
        'rate',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'order_item_id' => 'integer',
            'tax_component_id' => 'integer',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
