<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantCustomer extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'merchant_id',
        'user_id',
        'customer_code',
        'name',
        'mobile_country_code',
        'mobile',
        'mobile_normalized',
        'email',
        'date_of_birth',
        'gender',
        'is_business_customer',
        'company_name',
        'gst_number',
        'notes',
        'status',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_business_customer' => 'boolean',
            'linked_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (MerchantCustomer $customer): void {
            $customer->orders()->update(['customer_id' => null]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(MerchantCustomerAddress::class, 'merchant_customer_id');
    }
}
