<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['payment_account_id', 'provider', 'provider_event_id', 'event_type', 'provider_payment_id', 'provider_order_id', 'amount_minor', 'currency', 'disposition', 'failure_reason', 'payload_hash', 'received_at', 'processed_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }
}
