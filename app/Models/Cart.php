<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'customer_id',
        'session_token',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MerchantCustomer::class, 'customer_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
