<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasUuid;

    protected $fillable = [
        'event_key',
        'channel',
        'subject',
        'body',
        'is_active',
        'variables',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'variables' => 'array',
            'metadata' => 'array',
        ];
    }
}
