<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantProfile extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'business_name',
        'legal_name',
        'business_type',
        'gst_number',
        'has_shop_license',
        'has_fssai',
        'contact_person_name',
        'contact_email',
        'contact_mobile',
        'alternate_mobile',
        'verification_status',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'status',
        'admin_note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'has_shop_license' => 'boolean',
            'has_fssai' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(MerchantAddress::class, 'merchant_id');
    }

    public function businessAddress(): HasOne
    {
        return $this->hasOne(MerchantAddress::class, 'merchant_id')
            ->where('address_type', 'business');
    }
}
