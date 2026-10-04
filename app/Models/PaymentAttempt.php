<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PaymentAttempt extends Model { public const CREATED='created'; public const PENDING='pending'; public const PAID='paid'; public const FAILED='failed'; public const ABANDONED='abandoned'; protected $fillable=['order_id','shop_id','payment_account_id','provider','provider_order_id','provider_payment_id','amount_minor','currency','status','failure_code','failure_message','initiated_at','paid_at','failed_at','metadata']; protected function casts(): array { return ['amount_minor'=>'integer','metadata'=>'array','initiated_at'=>'datetime','paid_at'=>'datetime','failed_at'=>'datetime']; } public function order(): BelongsTo{return $this->belongsTo(Order::class);} public function shop(): BelongsTo{return $this->belongsTo(Shop::class);} public function paymentAccount(): BelongsTo{return $this->belongsTo(PaymentAccount::class);} }
