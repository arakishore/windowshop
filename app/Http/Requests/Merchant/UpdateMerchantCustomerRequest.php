<?php

namespace App\Http\Requests\Merchant;

use App\Http\Requests\Merchant\StoreMerchantCustomerRequest;
use App\Models\MerchantCustomer;
use App\Support\Validation\MerchantCustomerRules;

class UpdateMerchantCustomerRequest extends StoreMerchantCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        abort_unless($customer instanceof MerchantCustomer, 404);

        return MerchantCustomerRules::rules($this->merchant()->getKey(), $customer->getKey());
    }
}
