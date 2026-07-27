<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderExchange extends Model
{
    use HasUuid;

    public const STATUS_COMPLETED = 'completed';

    public const SETTLEMENT_EVEN = 'even';
    public const SETTLEMENT_COLLECT_EXTRA = 'collect_extra';
    public const SETTLEMENT_REFUND_BALANCE = 'refund_balance';
    public const SETTLEMENT_CREDIT_ADJUSTMENT = 'credit_adjustment';

    protected $fillable = [
        'uuid',
        'exchange_number',
        'original_order_id',
        'replacement_order_id',
        'merchant_id',
        'shop_id',
        'returned_total',
        'replacement_total',
        'difference_amount',
        'amount_collected',
        'amount_refunded',
        'credit_adjustment_amount',
        'settlement_type',
        'settlement_method',
        'status',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'returned_total' => 'decimal:2',
            'replacement_total' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'amount_collected' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'credit_adjustment_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function originalOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'original_order_id');
    }

    public function replacementOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'replacement_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderExchangeReturnItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
