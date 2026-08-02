<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderStatus extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const CODE_PENDING = 'pending';
    public const CODE_CONFIRMED = 'confirmed';
    public const CODE_PROCESSING = 'processing';
    public const CODE_PACKED = 'packed';
    public const CODE_READY_FOR_PICKUP = 'ready_for_pickup';
    public const CODE_SHIPPED = 'shipped';
    public const CODE_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const CODE_DELIVERED = 'delivered';
    public const CODE_COMPLETED = 'completed';
    public const CODE_CANCELLED = 'cancelled';
    public const CODE_PARTIALLY_CANCELLED = 'partially_cancelled';
    public const CODE_RETURN_REQUESTED = 'return_requested';
    public const CODE_RETURN_APPROVED = 'return_approved';
    public const CODE_RETURN_REJECTED = 'return_rejected';
    public const CODE_RETURN_IN_TRANSIT = 'return_in_transit';
    public const CODE_RETURN_RECEIVED = 'return_received';
    public const CODE_PARTIALLY_RETURNED = 'partially_returned';
    public const CODE_RETURNED = 'returned';
    public const CODE_EXCHANGE_REQUESTED = 'exchange_requested';
    public const CODE_EXCHANGE_APPROVED = 'exchange_approved';
    public const CODE_EXCHANGE_REJECTED = 'exchange_rejected';
    public const CODE_PARTIALLY_EXCHANGED = 'partially_exchanged';
    public const CODE_EXCHANGED = 'exchanged';
    public const CODE_FAILED = 'failed';

    public const CATEGORY_OPEN = 'open';
    public const CATEGORY_PROCESSING = 'processing';
    public const CATEGORY_SHIPPING = 'shipping';
    public const CATEGORY_FULFILLED = 'fulfilled';
    public const CATEGORY_CANCELLATION = 'cancellation';
    public const CATEGORY_RETURN = 'return';
    public const CATEGORY_EXCHANGE = 'exchange';
    public const CATEGORY_FAILED = 'failed';

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
        'customer_label',
        'description',
        'internal_notes',
        'category',
        'badge_type',
        'sort_order',
        'is_system',
        'is_terminal',
        'customer_visible',
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
            'customer_visible' => 'boolean',
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

    public function scopeCustomerVisible(Builder $query): Builder
    {
        return $query->where('customer_visible', true);
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
        return [
            self::CATEGORY_OPEN,
            self::CATEGORY_PROCESSING,
            self::CATEGORY_SHIPPING,
            self::CATEGORY_FULFILLED,
            self::CATEGORY_CANCELLATION,
            self::CATEGORY_RETURN,
            self::CATEGORY_EXCHANGE,
            self::CATEGORY_FAILED,
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
     * @return array<string, array{name: string, customer_label: string, description: string, category: string, badge_type: string, sort_order: int, is_terminal: bool, internal_notes?: string}>
     */
    public static function systemDefaults(): array
    {
        return [
            self::CODE_PENDING => [
                'name' => 'Pending',
                'customer_label' => 'Order Pending',
                'category' => self::CATEGORY_OPEN,
                'badge_type' => self::BADGE_SECONDARY,
                'sort_order' => 10,
                'is_terminal' => false,
                'description' => 'Order has been created but has not yet been confirmed by the merchant.',
            ],
            self::CODE_CONFIRMED => [
                'name' => 'Confirmed',
                'customer_label' => 'Order Confirmed',
                'category' => self::CATEGORY_OPEN,
                'badge_type' => self::BADGE_PRIMARY,
                'sort_order' => 20,
                'is_terminal' => false,
                'description' => 'Merchant has accepted the order and it is ready for processing.',
            ],
            self::CODE_PROCESSING => [
                'name' => 'Processing',
                'customer_label' => 'Order Processing',
                'category' => self::CATEGORY_PROCESSING,
                'badge_type' => self::BADGE_INFO,
                'sort_order' => 30,
                'is_terminal' => false,
                'description' => 'Order is currently being prepared or processed.',
            ],
            self::CODE_PACKED => [
                'name' => 'Packed',
                'customer_label' => 'Packed',
                'category' => self::CATEGORY_PROCESSING,
                'badge_type' => self::BADGE_INFO,
                'sort_order' => 40,
                'is_terminal' => false,
                'description' => 'Order has been packed and is waiting for pickup or dispatch.',
            ],
            self::CODE_READY_FOR_PICKUP => [
                'name' => 'Ready for Pickup',
                'customer_label' => 'Ready for Pickup',
                'category' => self::CATEGORY_PROCESSING,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 50,
                'is_terminal' => false,
                'description' => 'Order is ready for customer pickup or courier collection.',
            ],
            self::CODE_SHIPPED => [
                'name' => 'Shipped',
                'customer_label' => 'Shipped',
                'category' => self::CATEGORY_SHIPPING,
                'badge_type' => self::BADGE_PRIMARY,
                'sort_order' => 60,
                'is_terminal' => false,
                'description' => 'Order has been dispatched from the merchant.',
            ],
            self::CODE_OUT_FOR_DELIVERY => [
                'name' => 'Out for Delivery',
                'customer_label' => 'Out for Delivery',
                'category' => self::CATEGORY_SHIPPING,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 70,
                'is_terminal' => false,
                'description' => 'Courier is delivering the order to the customer.',
            ],
            self::CODE_DELIVERED => [
                'name' => 'Delivered',
                'customer_label' => 'Delivered',
                'category' => self::CATEGORY_FULFILLED,
                'badge_type' => self::BADGE_SUCCESS,
                'sort_order' => 80,
                'is_terminal' => false,
                'description' => 'Customer has received the order successfully.',
            ],
            self::CODE_COMPLETED => [
                'name' => 'Completed',
                'customer_label' => 'Order Completed',
                'category' => self::CATEGORY_FULFILLED,
                'badge_type' => self::BADGE_SUCCESS,
                'sort_order' => 90,
                'is_terminal' => true,
                'description' => 'Order lifecycle has been completed and no further fulfilment is pending.',
                'internal_notes' => 'Used by POS, reports and revenue calculations. Do not delete or rename the code.',
            ],
            self::CODE_CANCELLED => [
                'name' => 'Cancelled',
                'customer_label' => 'Order Cancelled',
                'category' => self::CATEGORY_CANCELLATION,
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 100,
                'is_terminal' => true,
                'description' => 'Entire order has been cancelled.',
            ],
            self::CODE_PARTIALLY_CANCELLED => [
                'name' => 'Partially Cancelled',
                'customer_label' => 'Partially Cancelled',
                'category' => self::CATEGORY_CANCELLATION,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 110,
                'is_terminal' => false,
                'description' => 'One or more order items have been cancelled while others remain active.',
            ],
            self::CODE_RETURN_REQUESTED => [
                'name' => 'Return Requested',
                'customer_label' => 'Return Requested',
                'category' => self::CATEGORY_RETURN,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 120,
                'is_terminal' => false,
                'description' => 'Customer has requested a return for one or more order items.',
            ],
            self::CODE_RETURN_APPROVED => [
                'name' => 'Return Approved',
                'customer_label' => 'Return Approved',
                'category' => self::CATEGORY_RETURN,
                'badge_type' => self::BADGE_PRIMARY,
                'sort_order' => 130,
                'is_terminal' => false,
                'description' => 'Return request has been approved by the merchant.',
            ],
            self::CODE_RETURN_REJECTED => [
                'name' => 'Return Rejected',
                'customer_label' => 'Return Rejected',
                'category' => self::CATEGORY_RETURN,
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 140,
                'is_terminal' => true,
                'description' => 'Return request has been rejected.',
            ],
            self::CODE_RETURN_IN_TRANSIT => [
                'name' => 'Return In Transit',
                'customer_label' => 'Return In Transit',
                'category' => self::CATEGORY_RETURN,
                'badge_type' => self::BADGE_INFO,
                'sort_order' => 150,
                'is_terminal' => false,
                'description' => 'Returned items are currently on their way back to the merchant.',
            ],
            self::CODE_RETURN_RECEIVED => [
                'name' => 'Return Received',
                'customer_label' => 'Return Received',
                'category' => self::CATEGORY_RETURN,
                'badge_type' => self::BADGE_INFO,
                'sort_order' => 160,
                'is_terminal' => false,
                'description' => 'Merchant has received the returned items and verification is pending.',
            ],
            self::CODE_PARTIALLY_RETURNED => [
                'name' => 'Partially Returned',
                'customer_label' => 'Partially Returned',
                'category' => self::CATEGORY_RETURN,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 170,
                'is_terminal' => false,
                'description' => 'Some items have been returned while others remain completed.',
            ],
            self::CODE_RETURNED => [
                'name' => 'Returned',
                'customer_label' => 'Returned',
                'category' => self::CATEGORY_RETURN,
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 180,
                'is_terminal' => true,
                'description' => 'Entire order has been returned successfully.',
            ],
            self::CODE_EXCHANGE_REQUESTED => [
                'name' => 'Exchange Requested',
                'customer_label' => 'Exchange Requested',
                'category' => self::CATEGORY_EXCHANGE,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 190,
                'is_terminal' => false,
                'description' => 'Customer has requested an exchange.',
            ],
            self::CODE_EXCHANGE_APPROVED => [
                'name' => 'Exchange Approved',
                'customer_label' => 'Exchange Approved',
                'category' => self::CATEGORY_EXCHANGE,
                'badge_type' => self::BADGE_PRIMARY,
                'sort_order' => 200,
                'is_terminal' => false,
                'description' => 'Exchange request has been approved.',
            ],
            self::CODE_EXCHANGE_REJECTED => [
                'name' => 'Exchange Rejected',
                'customer_label' => 'Exchange Rejected',
                'category' => self::CATEGORY_EXCHANGE,
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 210,
                'is_terminal' => true,
                'description' => 'Exchange request has been rejected.',
            ],
            self::CODE_PARTIALLY_EXCHANGED => [
                'name' => 'Partially Exchanged',
                'customer_label' => 'Partially Exchanged',
                'category' => self::CATEGORY_EXCHANGE,
                'badge_type' => self::BADGE_WARNING,
                'sort_order' => 220,
                'is_terminal' => false,
                'description' => 'Some order items have been exchanged while others remain unchanged.',
            ],
            self::CODE_EXCHANGED => [
                'name' => 'Exchanged',
                'customer_label' => 'Exchanged',
                'category' => self::CATEGORY_EXCHANGE,
                'badge_type' => self::BADGE_SUCCESS,
                'sort_order' => 230,
                'is_terminal' => true,
                'description' => 'Exchange process has been completed successfully.',
            ],
            self::CODE_FAILED => [
                'name' => 'Failed',
                'customer_label' => 'Order Failed',
                'category' => self::CATEGORY_FAILED,
                'badge_type' => self::BADGE_DANGER,
                'sort_order' => 240,
                'is_terminal' => true,
                'description' => 'Order could not be completed because of a system, payment or processing failure.',
            ],
        ];
    }
}
