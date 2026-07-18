<?php

namespace App\Services\Merchant;

use App\Models\MerchantCustomer;
use App\Models\MerchantProfile;

class CustomerCodeGenerator
{
    private const PREFIX = 'CUS-';

    public function generate(MerchantProfile|int $merchant): string
    {
        $merchantId = $merchant instanceof MerchantProfile ? $merchant->getKey() : $merchant;

        $latestCode = MerchantCustomer::query()
            ->withTrashed()
            ->where('merchant_id', $merchantId)
            ->where('customer_code', 'like', self::PREFIX.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('customer_code');

        return self::PREFIX.str_pad((string) ($this->sequenceFromCode((string) $latestCode) + 1), 6, '0', STR_PAD_LEFT);
    }

    private function sequenceFromCode(string $code): int
    {
        $suffix = substr($code, strlen(self::PREFIX));

        return ctype_digit($suffix) ? (int) $suffix : 0;
    }
}
