<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReturnPolicy extends Model
{
    protected $fillable = [
        'product_id',
        'refund_allowed',
        'refund_window_days',
        'exchange_allowed',
        'exchange_window_days',
    ];

    protected function casts(): array
    {
        return [
            'refund_allowed' => 'boolean',
            'refund_window_days' => 'integer',
            'exchange_allowed' => 'boolean',
            'exchange_window_days' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
