<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentStatus extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const CODE_PENDING = 'pending';
    public const CODE_PARTIALLY_PAID = 'partially_paid';
    public const CODE_PAID = 'paid';
    public const CODE_FAILED = 'failed';
    public const CODE_CANCELLED = 'cancelled';
    public const CODE_PARTIALLY_REFUNDED = 'partially_refunded';
    public const CODE_REFUNDED = 'refunded';
    public const CODE_CHARGEBACK = 'chargeback';

    public const CATEGORY_AWAITING_PAYMENT = 'awaiting_payment';
    public const CATEGORY_PARTIALLY_PAID = 'partially_paid';
    public const CATEGORY_PAID = 'paid';
    public const CATEGORY_FAILED = 'failed';
    public const CATEGORY_REFUNDED = 'refunded';
    public const CATEGORY_DISPUTED = 'disputed';

    public const BADGE_PRIMARY = 'primary';
    public const BADGE_SECONDARY = 'secondary';
    public const BADGE_SUCCESS = 'success';
    public const BADGE_DANGER = 'danger';
    public const BADGE_WARNING = 'warning';
    public const BADGE_INFO = 'info';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'category',
        'description',
        'category_description',
        'badge_type',
        'sort_order',
        'is_system',
        'is_terminal',
        'merchant_visible',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_terminal' => 'boolean',
            'merchant_visible' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('deleted_at');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeMerchantVisible(Builder $query): Builder
    {
        return $query->where('merchant_visible', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    public function safeBadgeClass(): string
    {
        return match ($this->badge_type) {
            self::BADGE_PRIMARY => 'bg-primary',
            self::BADGE_SUCCESS => 'bg-success',
            self::BADGE_DANGER => 'bg-danger',
            self::BADGE_WARNING => 'bg-warning',
            self::BADGE_INFO => 'bg-info',
            default => 'bg-secondary',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return array_keys(self::categoryDescriptions());
    }

    /**
     * @return array<string, string>
     */
    public static function categoryDescriptions(): array
    {
        return [
            self::CATEGORY_AWAITING_PAYMENT => 'Waiting for payment.',
            self::CATEGORY_PARTIALLY_PAID => 'Partial payment received.',
            self::CATEGORY_PAID => 'Payment completed successfully.',
            self::CATEGORY_FAILED => 'Payment failed or cancelled.',
            self::CATEGORY_REFUNDED => 'Full or partial refund completed.',
            self::CATEGORY_DISPUTED => 'Payment reversed by bank or payment gateway.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function badgeTypes(): array
    {
        return [
            self::BADGE_PRIMARY,
            self::BADGE_SECONDARY,
            self::BADGE_SUCCESS,
            self::BADGE_DANGER,
            self::BADGE_WARNING,
            self::BADGE_INFO,
        ];
    }

    /**
     * @return array<string, array{name: string, category: string, description: string, badge_type: string, sort_order: int, is_terminal: bool}>
     */
    public static function systemDefaults(): array
    {
        return [
            self::CODE_PENDING => [
                'name' => 'Pending',
                'category' => self::CATEGORY_AWAITING_PAYMENT,
                'description' => 'Payment has not yet been received.',
                'badge_type' => self::BADGE_SECONDARY,
                'sort_order' => 10,
                'is_terminal' => false,
            ],
            self::CODE_PARTIALLY_PAID => [
                'name' => 'Partially Paid',
                'category' => self::CATEGORY_PARTIALLY_PAID,
                'description' => 'Partial payment has been received. Remaining balance is still outstanding.',
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 20,
                'is_terminal' => false,
            ],
            self::CODE_PAID => [
                'name' => 'Paid',
                'category' => self::CATEGORY_PAID,
                'description' => 'Full payment has been received successfully.',
                'badge_type' => self::BADGE_SUCCESS,
                'sort_order' => 30,
                'is_terminal' => true,
            ],
            self::CODE_FAILED => [
                'name' => 'Failed',
                'category' => self::CATEGORY_FAILED,
                'description' => 'Payment attempt was unsuccessful.',
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 40,
                'is_terminal' => true,
            ],
            self::CODE_CANCELLED => [
                'name' => 'Cancelled',
                'category' => self::CATEGORY_FAILED,
                'description' => 'Payment was cancelled before completion.',
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 50,
                'is_terminal' => true,
            ],
            self::CODE_PARTIALLY_REFUNDED => [
                'name' => 'Partially Refunded',
                'category' => self::CATEGORY_REFUNDED,
                'description' => 'Part of the payment has been refunded.',
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 60,
                'is_terminal' => false,
            ],
            self::CODE_REFUNDED => [
                'name' => 'Refunded',
                'category' => self::CATEGORY_REFUNDED,
                'description' => 'Full payment has been refunded.',
                'badge_type' => self::BADGE_INFO,
                'sort_order' => 70,
                'is_terminal' => true,
            ],
            self::CODE_CHARGEBACK => [
                'name' => 'Chargeback',
                'category' => self::CATEGORY_DISPUTED,
                'description' => "Payment was reversed by the customer's bank or payment provider.",
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 80,
                'is_terminal' => true,
            ],
        ];
    }
}
