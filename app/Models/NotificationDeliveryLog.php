<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'delivery_key',
        'notification_key',
        'recipient_type',
        'recipient_id',
        'shop_id',
        'merchant_id',
        'related_type',
        'related_id',
        'channel',
        'destination',
        'provider_mode',
        'provider',
        'status',
        'error_summary',
        'attempted_at',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function maskedDestination(): ?string
    {
        $destination = $this->getAttribute('destination');

        if (! is_string($destination) || $destination === '') {
            return null;
        }

        if (str_contains($destination, '@')) {
            [$local, $domain] = explode('@', $destination, 2);

            return mb_substr($local, 0, 1).'***@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $destination) ?: '';

        return strlen($digits) > 4 ? '***'.substr($digits, -4) : '***';
    }

    public function toArray(): array
    {
        $values = parent::toArray();

        if (array_key_exists('destination', $values)) {
            $values['destination'] = $this->maskedDestination();
        }

        return $values;
    }
}
