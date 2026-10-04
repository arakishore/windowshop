<?php
namespace App\Services\Payment;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\Shop;
use Illuminate\Validation\ValidationException;
class PaymentAttemptService { public function create(Order $order,Shop $shop,PaymentAccount $account,int $amountMinor,string $currency='INR',array $data=[]): PaymentAttempt { if((int)$order->shop_id!==(int)$shop->getKey() || (int)$account->merchant_id!==(int)$shop->merchant_id || !$account->shops()->whereKey($shop)->exists() || ($data['provider'] ?? $account->provider)!==$account->provider || $amountMinor<=0 || strlen($currency)!==3 || !in_array($data['status']??PaymentAttempt::CREATED,[PaymentAttempt::CREATED,PaymentAttempt::PENDING,PaymentAttempt::PAID,PaymentAttempt::FAILED,PaymentAttempt::ABANDONED],true)) throw ValidationException::withMessages(['payment_attempt'=>'Invalid payment attempt configuration.']); return PaymentAttempt::create([...$data,'order_id'=>$order->getKey(),'shop_id'=>$shop->getKey(),'payment_account_id'=>$account->getKey(),'provider'=>$account->provider,'amount_minor'=>$amountMinor,'currency'=>strtoupper($currency)]); } }
