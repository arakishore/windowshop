<?php
namespace App\Services\Payment;
use App\Models\MerchantProfile;
use App\Models\PaymentAccount;
use App\Models\Shop;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
class PaymentAccountService {
 public function create(MerchantProfile $merchant,array $data): PaymentAccount { $account=PaymentAccount::create([...$data,'merchant_id'=>$merchant->getKey()]); $this->secrets($account,$data); return $account->fresh(); }
 public function updateSecrets(PaymentAccount $account,?string $secret=null,?string $webhookSecret=null): void { $this->secrets($account,['secret'=>$secret,'webhook_secret'=>$webhookSecret]); }
 public function secret(PaymentAccount $account): ?string { return $this->decrypt($account->secret); }
 public function webhookSecret(PaymentAccount $account): ?string { return $this->decrypt($account->webhook_secret); }
 public function map(PaymentAccount $account,Shop $shop): void { if((int)$account->merchant_id !== (int)$shop->merchant_id) throw ValidationException::withMessages(['payment_account'=>'Payment account belongs to another merchant.']); $account->shops()->syncWithoutDetaching([$shop->getKey()]); }
 private function secrets(PaymentAccount $account,array $data): void { $values=[]; foreach(['secret','webhook_secret'] as $key){ if(array_key_exists($key,$data) && filled($data[$key])) $values[$key]=Crypt::encryptString((string)$data[$key]); } if($values)$account->forceFill($values)->save(); }
 private function decrypt(?string $value): ?string { if(blank($value)) return null; try{return Crypt::decryptString($value);}catch(\Throwable){return null;} }
}
