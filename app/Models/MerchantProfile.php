<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'business_name',
        'legal_name',
        'business_type',
        'gst_number',
        'pan_number',
        'contact_person_name',
        'contact_email',
        'contact_mobile',
        'alternate_mobile',
        'website_url',
        'logo_path',
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

    public function addresses(): HasMany
    {
        return $this->hasMany(MerchantAddress::class, 'merchant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MerchantDocument::class, 'merchant_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(MerchantBankAccount::class, 'merchant_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(MerchantVerification::class, 'merchant_id');
    }
}
