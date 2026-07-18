<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuid, SoftDeletes;

    public const SOURCE_POS = 'pos';
    public const SOURCE_CUSTOMER_APP = 'customer_app';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_API = 'api';

    public const FULFILMENT_COUNTER = 'counter';
    public const FULFILMENT_PICKUP = 'pickup';
    public const FULFILMENT_DELIVERY = 'delivery';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_UPI = 'upi';
    public const PAYMENT_METHOD_CARD = 'card';
    public const PAYMENT_METHOD_WALLET = 'wallet';
    public const PAYMENT_METHOD_OTHER = 'other';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    public const DISCOUNT_TYPE_PERCENT = 'percent';
    public const DISCOUNT_TYPE_AMOUNT = 'amount';

    protected $fillable = [
        'uuid',
        'order_number',
        'merchant_id',
        'shop_id',
        'customer_id',
        'shipping_address_id',
        'created_source',
        'fulfilment_type',
        'order_status',
        'payment_method',
        'payment_reference',
        'upi_txn',
        'terminal_id',
        'payment_status',
        'currency_code',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'rounding_adjustment',
        'grand_total',
        'order_discount_type',
        'order_discount_value',
        'order_discount_amount',
        'order_discount_reason',
        'order_discount_note',
        'amount_paid',
        'change_amount',
        'elapsed_seconds',
        'customer_name',
        'customer_mobile',
        'customer_email',
        'shipping_recipient_name',
        'shipping_mobile_country_code',
        'shipping_mobile',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_landmark',
        'shipping_city',
        'shipping_state',
        'shipping_country',
        'shipping_postal_code',
        'remarks',
        'created_by',
        'updated_by',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'rounding_adjustment' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'order_discount_value' => 'decimal:2',
            'order_discount_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'elapsed_seconds' => 'integer',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'deleted_at' => 'datetime',
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

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MerchantCustomer::class, 'customer_id')->withTrashed();
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(MerchantCustomerAddress::class, 'shipping_address_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function totals(): HasMany
    {
        return $this->hasMany(OrderTotal::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
