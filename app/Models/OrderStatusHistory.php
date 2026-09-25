<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    public const ACTION_REFUND_PROCESSED = 'refund_processed';

    public const ACTION_EXCHANGE_PROCESSED = 'exchange_processed';

    public const ACTION_UPI_PAYMENT_CONFIRMED = 'upi_payment_confirmed';

    public const ACTION_UPI_PAYMENT_REJECTED = 'upi_payment_rejected';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}
