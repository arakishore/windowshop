<?php

namespace App\Services\Merchant;

use App\Models\MerchantCustomer;
use App\Models\MerchantProfile;
use App\Services\Customer\CustomerIdentityResolver;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MerchantCustomerService
{
    public function __construct(
        private readonly CustomerCodeGenerator $customerCodeGenerator,
        private readonly MobileNumberNormalizer $mobileNumberNormalizer,
        private readonly CustomerIdentityResolver $customerIdentityResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(MerchantProfile $merchant, array $data): MerchantCustomer
    {
        return $this->retryOnDuplicate(function () use ($merchant, $data): MerchantCustomer {
            return DB::transaction(function () use ($merchant, $data): MerchantCustomer {
                $customer = $this->customerIdentityResolver->resolveOrCreate($data);
                $payload = $this->relationshipPayload($data);
                $payload['merchant_id'] = $merchant->getKey();
                $payload['customer_id'] = $customer->getKey();
                $payload['customer_code'] = $data['customer_code'] ?? $this->customerCodeGenerator->generate($merchant);
                $payload['linked_at'] = $data['linked_at'] ?? now();

                return MerchantCustomer::query()->firstOrCreate([
                    'merchant_id' => $merchant->getKey(),
                    'customer_id' => $customer->getKey(),
                ], $payload)->loadMissing('customer');
            });
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromPos(MerchantProfile $merchant, array $data): MerchantCustomer
    {
        return $this->retryOnDuplicate(function () use ($merchant, $data): MerchantCustomer {
            return DB::transaction(function () use ($merchant, $data): MerchantCustomer {
                $customer = $this->customerIdentityResolver->resolveOrCreateForPos($data);
                $payload = $this->relationshipPayload($data);

                $payload['merchant_id'] = $merchant->getKey();
                $payload['customer_id'] = $customer->getKey();
                $payload['customer_code'] = $data['customer_code'] ?? $this->customerCodeGenerator->generate($merchant);
                $payload['linked_at'] = $data['linked_at'] ?? now();

                return MerchantCustomer::query()->firstOrCreate([
                    'merchant_id' => $merchant->getKey(),
                    'customer_id' => $customer->getKey(),
                ], $payload)->loadMissing('customer');
            });
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(MerchantCustomer $customer, array $data): MerchantCustomer
    {
        return DB::transaction(function () use ($customer, $data): MerchantCustomer {
            $customer->loadMissing('customer');
            $customer->customer?->fill($this->customerPayload($data))->save();
            $customer->fill($this->relationshipPayload($data, false))->save();

            return $customer->refresh()->loadMissing('customer');
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function relationshipPayload(array $data, bool $creating = true): array
    {
        $fields = [
            'notes',
            'trust_status',
            'status',
            'linked_at',
        ];

        $payload = [];

        foreach ($fields as $field) {
            if ($creating || array_key_exists($field, $data)) {
                $payload[$field] = $data[$field] ?? null;
            }
        }

        if ($creating && ! isset($payload['status'])) {
            $payload['status'] = MerchantCustomer::STATUS_ACTIVE;
        }

        if ($creating && ! isset($payload['trust_status'])) {
            $payload['trust_status'] = 'normal';
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function customerPayload(array $data): array
    {
        $payload = [];
        foreach ([
            'name',
            'email',
            'date_of_birth',
            'gender',
            'is_business_customer',
            'company_name',
            'gst_number',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('is_business_customer', $payload)) {
            $payload['is_business_customer'] = (bool) $payload['is_business_customer'];

            if (! $payload['is_business_customer']) {
                $payload['company_name'] = null;
                $payload['gst_number'] = null;
            }
        }

        if (array_key_exists('mobile', $data) || array_key_exists('mobile_country_code', $data)) {
            $mobile = $this->mobileNumberNormalizer->normalize(
                (string) ($data['mobile'] ?? ''),
                isset($data['mobile_country_code']) ? (string) $data['mobile_country_code'] : null,
            );

            $payload['mobile_country_code'] = $mobile['mobile_normalized'] !== '' ? $mobile['country_code'] : null;
            $payload['mobile'] = $mobile['mobile'] !== '' ? $mobile['mobile'] : null;
            $payload['mobile_normalized'] = $mobile['mobile_normalized'] !== '' ? $mobile['mobile_normalized'] : null;
        }

        return $payload;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function retryOnDuplicate(callable $callback): mixed
    {
        for ($attempt = 1; $attempt <= 3; $attempt += 1) {
            try {
                return $callback();
            } catch (QueryException $exception) {
                if ($attempt >= 3 || ! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                usleep(random_int(1000, 5000));
            }
        }

        throw new \RuntimeException('Unable to create merchant customer after retrying duplicate code generation.');
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000' || $driverCode === '1062' || $driverCode === '19';
    }
}
