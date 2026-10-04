<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PaymentAccount extends Model { protected $fillable=['merchant_id','provider','name','mode','enabled','public_key','provider_account_reference']; protected $hidden=['secret','webhook_secret']; protected function casts(): array { return ['enabled'=>'boolean']; } public function merchant(): BelongsTo { return $this->belongsTo(MerchantProfile::class); } public function shops(): BelongsToMany { return $this->belongsToMany(Shop::class,'payment_account_shop')->withTimestamps(); } public function attempts(): HasMany { return $this->hasMany(PaymentAttempt::class); } }
