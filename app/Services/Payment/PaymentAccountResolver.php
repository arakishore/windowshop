<?php

namespace App\Services\Payment;

use App\Models\PaymentAccount;
use App\Models\Shop;

class PaymentAccountResolver
{
    public function __construct(private readonly PaymentAccountService $accounts) {}

    public function resolveForShop(Shop $shop, string $provider, string $mode): ?PaymentAccount
    {
        $account = $shop->paymentAccounts()
            ->where('merchant_id', $shop->merchant_id)
            ->where('provider', $provider)
            ->where('mode', $mode)
            ->where('enabled', true)
            ->whereNotNull('public_key')
            ->first();

        return $account && filled($this->accounts->secret($account)) ? $account : null;
    }
}
