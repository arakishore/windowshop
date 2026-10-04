<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentAccount extends Model
{
    protected $fillable = ['merchant_id', 'provider', 'name', 'mode', 'enabled', 'public_key', 'provider_account_reference'];

    protected $hidden = ['secret', 'webhook_secret', 'webhook_token'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentAccount $account): void {
            $account->webhook_token ??= Str::random(64);
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'payment_account_shop')->withTimestamps();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }
}
