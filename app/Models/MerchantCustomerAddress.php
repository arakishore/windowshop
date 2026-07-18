<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantCustomerAddress extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'merchant_customer_id',
        'label',
        'recipient_name',
        'recipient_mobile_country_code',
        'recipient_mobile',
        'recipient_mobile_normalized',
        'address_line_1',
        'address_line_2',
        'landmark',
        'country_id',
        'state_id',
        'city_id',
        'postal_code',
        'is_default_shipping',
        'is_default_billing',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MerchantCustomer::class, 'merchant_customer_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(LocCountry::class, 'country_id')->withTrashed();
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(LocState::class, 'state_id')->withTrashed();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(LocCity::class, 'city_id')->withTrashed();
    }
}
