<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantCancellationReason extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const CODE_OTHER = 'other';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'code',
        'name',
        'description',
        'internal_notes',
        'sort_order',
        'customer_selectable',
        'merchant_selectable',
        'requires_comment',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'customer_selectable' => 'boolean',
            'merchant_selectable' => 'boolean',
            'requires_comment' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('deleted_at');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }

    /**
     * @return array<string, array{name: string, description: string, internal_notes: string, sort_order: int, customer_selectable: bool, merchant_selectable: bool, requires_comment: bool, status: string}>
     */
    public static function defaults(): array
    {
        return [
            'customer_requested' => [
                'name' => 'Customer Requested Cancellation',
                'description' => 'The customer asked to cancel the order before fulfilment was completed.',
                'internal_notes' => 'Customer-originated cancellation. A comment may be added when extra context is required.',
                'sort_order' => 10,
                'customer_selectable' => true,
                'merchant_selectable' => true,
                'requires_comment' => false,
                'status' => self::STATUS_ACTIVE,
            ],
            'ordered_by_mistake' => [
                'name' => 'Ordered by Mistake',
                'description' => 'The customer placed the order accidentally or selected incorrect items.',
                'internal_notes' => 'Customer-originated cancellation. Normally used before shipping or pickup completion.',
                'sort_order' => 20,
                'customer_selectable' => true,
                'merchant_selectable' => true,
                'requires_comment' => false,
                'status' => self::STATUS_ACTIVE,
            ],
            'duplicate_order' => [
                'name' => 'Duplicate Order',
                'description' => 'The order duplicates another order already placed by the customer.',
                'internal_notes' => 'Verify the related order before cancelling.',
                'sort_order' => 30,
                'customer_selectable' => true,
                'merchant_selectable' => true,
                'requires_comment' => false,
                'status' => self::STATUS_ACTIVE,
            ],
            'out_of_stock' => [
                'name' => 'Product Out of Stock',
                'description' => 'One or more products required for the order are unavailable.',
                'internal_notes' => 'Merchant-originated cancellation. Inventory should be reviewed separately.',
                'sort_order' => 40,
                'customer_selectable' => false,
                'merchant_selectable' => true,
                'requires_comment' => false,
                'status' => self::STATUS_ACTIVE,
            ],
            'unable_to_fulfil' => [
                'name' => 'Unable to Fulfil Order',
                'description' => 'The merchant is unable to complete the order.',
                'internal_notes' => 'Use when no more specific cancellation reason applies. A comment is required.',
                'sort_order' => 50,
                'customer_selectable' => false,
                'merchant_selectable' => true,
                'requires_comment' => true,
                'status' => self::STATUS_ACTIVE,
            ],
            'store_closed' => [
                'name' => 'Store Closed',
                'description' => 'The order cannot be completed because the store is temporarily unavailable or closed.',
                'internal_notes' => 'Merchant-originated cancellation.',
                'sort_order' => 60,
                'customer_selectable' => false,
                'merchant_selectable' => true,
                'requires_comment' => false,
                'status' => self::STATUS_ACTIVE,
            ],
            'payment_not_received' => [
                'name' => 'Payment Not Received',
                'description' => 'The required payment was not received within the expected time.',
                'internal_notes' => 'Do not use this reason as a replacement for payment status management.',
                'sort_order' => 70,
                'customer_selectable' => false,
                'merchant_selectable' => true,
                'requires_comment' => false,
                'status' => self::STATUS_ACTIVE,
            ],
            'suspected_fraud' => [
                'name' => 'Suspected Fraud',
                'description' => 'The order requires cancellation because fraudulent or suspicious activity is suspected.',
                'internal_notes' => 'Internal reason. Should not normally be exposed to customers.',
                'sort_order' => 80,
                'customer_selectable' => false,
                'merchant_selectable' => true,
                'requires_comment' => true,
                'status' => self::STATUS_ACTIVE,
            ],
            'system_error' => [
                'name' => 'System Error',
                'description' => 'The order cannot continue because of a technical or system-related issue.',
                'internal_notes' => 'A comment is required to record the exact issue.',
                'sort_order' => 90,
                'customer_selectable' => false,
                'merchant_selectable' => true,
                'requires_comment' => true,
                'status' => self::STATUS_ACTIVE,
            ],
            self::CODE_OTHER => [
                'name' => 'Other',
                'description' => 'The cancellation reason does not match any predefined option.',
                'internal_notes' => 'A comment is mandatory.',
                'sort_order' => 999,
                'customer_selectable' => true,
                'merchant_selectable' => true,
                'requires_comment' => true,
                'status' => self::STATUS_ACTIVE,
            ],
        ];
    }
}
