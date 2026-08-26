<?php

namespace App\Services\Customer;

use App\Models\CustomerAddress;
use App\Models\Customer;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    public function __construct(
        private readonly MobileNumberNormalizer $mobileNumberNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerAddress {
            $address = $customer->addresses()->create($this->payload($data));
            $this->syncDefaults($address);

            return $address->refresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($address, $data): CustomerAddress {
            $address->fill($this->payload($data))->save();
            $this->syncDefaults($address);

            return $address->refresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $mobile = $this->mobileNumberNormalizer->normalize(
            (string) ($data['recipient_mobile'] ?? ''),
            isset($data['recipient_mobile_country_code']) ? (string) $data['recipient_mobile_country_code'] : null,
        );

        return [
            'label' => $data['label'],
            'recipient_name' => $data['recipient_name'],
            'recipient_mobile_country_code' => $mobile['country_code'],
            'recipient_mobile' => $mobile['mobile'],
            'recipient_mobile_normalized' => $mobile['mobile_normalized'],
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'landmark' => $data['landmark'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'is_default_shipping' => (bool) ($data['is_default_shipping'] ?? false),
            'is_default_billing' => (bool) ($data['is_default_billing'] ?? false),
            'status' => $data['status'] ?? CustomerAddress::STATUS_ACTIVE,
        ];
    }

    private function syncDefaults(CustomerAddress $address): void
    {
        if ($address->is_default_shipping) {
            CustomerAddress::query()
                ->where('customer_id', $address->customer_id)
                ->whereKeyNot($address->getKey())
                ->update(['is_default_shipping' => false]);
        }

        if ($address->is_default_billing) {
            CustomerAddress::query()
                ->where('customer_id', $address->customer_id)
                ->whereKeyNot($address->getKey())
                ->update(['is_default_billing' => false]);
        }
    }
}
