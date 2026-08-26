<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantCustomer extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'customer_code',
        'notes',
        'trust_status',
        'status',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
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

    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, Customer::class, 'id', 'id', 'customer_id', 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'customer_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id', 'customer_id');
    }

    public function getNameAttribute(): ?string
    {
        return $this->customer?->name;
    }

    public function getMobileCountryCodeAttribute(): ?string
    {
        return $this->customer?->mobile_country_code;
    }

    public function getMobileAttribute(): ?string
    {
        return $this->customer?->mobile;
    }

    public function getMobileNormalizedAttribute(): ?string
    {
        return $this->customer?->mobile_normalized;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->customer?->email;
    }

    public function getDateOfBirthAttribute(): mixed
    {
        return $this->customer?->date_of_birth;
    }

    public function getGenderAttribute(): ?string
    {
        return $this->customer?->gender;
    }

    public function getIsBusinessCustomerAttribute(): bool
    {
        return (bool) $this->customer?->is_business_customer;
    }

    public function getCompanyNameAttribute(): ?string
    {
        return $this->customer?->company_name;
    }

    public function getGstNumberAttribute(): ?string
    {
        return $this->customer?->gst_number;
    }
}
