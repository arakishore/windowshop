<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRefund extends Model
{
    use HasUuid;

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'uuid',
        'refund_number',
        'order_id',
        'merchant_id',
        'shop_id',
        'return_reason_id',
        'reason_name',
        'refund_method',
        'refund_subtotal',
        'refund_tax',
        'refund_total',
        'status',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'refund_subtotal' => 'decimal:2',
            'refund_tax' => 'decimal:2',
            'refund_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'return_reason_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderRefundItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
