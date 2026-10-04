<?php
namespace App\Services\Payment;
use App\Models\PaymentAccount;
use App\Models\Shop;
class PaymentAccountResolver { public function resolveForShop(Shop $shop,string $provider,string $mode): ?PaymentAccount { return $shop->paymentAccounts()->where('merchant_id',$shop->merchant_id)->where('provider',$provider)->where('mode',$mode)->where('enabled',true)->whereNotNull('public_key')->first(); } }
