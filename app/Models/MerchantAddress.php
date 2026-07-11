<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantAddress extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'merchant_id',
        'address_type',
        'address_line_1',
        'address_line_2',
        'landmark',
        'country_id',
        'state_id',
        'city_id',
        'pincode',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }
}
