<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderComment extends Model
{
    use HasUuid, SoftDeletes;

    public const AUTHOR_MERCHANT = 'merchant';
    public const AUTHOR_CUSTOMER = 'customer';
    public const AUTHOR_SYSTEM = 'system';
    public const AUTHOR_ADMIN = 'admin';

    public const VISIBILITY_MERCHANT_ONLY = 'merchant_only';
    public const VISIBILITY_CUSTOMER = 'customer';

    protected $fillable = [
        'uuid',
        'order_id',
        'author_type',
        'comment',
        'visibility',
        'notify_customer',
        'notify_email',
        'notify_sms',
        'notify_whatsapp',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'notify_customer' => 'boolean',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function authorTypes(): array
    {
        return array_keys((array) config('order_comments.author_types', []));
    }

    /**
     * @return array<int, string>
     */
    public static function visibilityOptions(): array
    {
        return array_keys((array) config('order_comments.visibilities', []));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
