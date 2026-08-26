<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Support\Str;

class CustomerIdentityResolver
{
    public function __construct(
        private readonly MobileNumberNormalizer $mobileNumberNormalizer,
    ) {
    }

    public function resolveOrCreateForPos(array $customerData): Customer
    {
        return $this->resolveOrCreate($customerData);
    }

    public function resolveOrCreateForUser(User $user): Customer
    {
        $data = [
            'user_id' => $user->getKey(),
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'status' => Customer::STATUS_ACTIVE,
        ];

        return $this->resolveOrCreate($data, $user);
    }

    /**
     * @param array<string, mixed> $customerData
     */
    public function resolveOrCreate(array $customerData, ?User $user = null): Customer
    {
        $mobile = $this->mobileNumberNormalizer->normalize(
            (string) ($customerData['mobile'] ?? ''),
            isset($customerData['mobile_country_code']) ? (string) $customerData['mobile_country_code'] : null,
        );
        $email = $this->normalizeEmail($customerData['email'] ?? null);
        $user ??= isset($customerData['user_id'])
            ? User::query()->find((int) $customerData['user_id'])
            : null;

        $customer = $user instanceof User ? $this->findCustomerByUser($user) : null;
        $customer ??= $mobile['mobile_normalized'] !== '' ? $this->findCustomerByMobile($mobile['mobile_normalized']) : null;
        $customer ??= $email !== null ? $this->findCustomerByEmail($email) : null;
        $user ??= $mobile['mobile_normalized'] !== '' ? $this->findUserByMobile($mobile['mobile_normalized']) : null;
        $user ??= $email !== null ? $this->findUserByEmail($email) : null;

        if ($customer instanceof Customer) {
            $updates = [];

            if ($user instanceof User && $customer->user_id === null) {
                $updates['user_id'] = $user->getKey();
            }

            foreach ($this->profilePayload($customerData, $mobile, $email) as $key => $value) {
                if ($value !== null && ($customer->{$key} === null || $customer->{$key} === '')) {
                    $updates[$key] = $value;
                }
            }

            if ($updates !== []) {
                $customer->forceFill($updates)->save();
            }

            return $customer->refresh();
        }

        return Customer::query()->create([
            ...$this->profilePayload($customerData, $mobile, $email),
            'user_id' => $user?->getKey(),
            'status' => $customerData['status'] ?? Customer::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param array<string, mixed> $customerData
     * @param array<string, string|null> $mobile
     * @return array<string, mixed>
     */
    private function profilePayload(array $customerData, array $mobile, ?string $email): array
    {
        $isBusinessCustomer = (bool) ($customerData['is_business_customer'] ?? false);

        return [
            'name' => $this->name($customerData, $mobile['mobile']),
            'mobile_country_code' => $mobile['mobile_normalized'] !== '' ? $mobile['country_code'] : null,
            'mobile' => $mobile['mobile'] !== '' ? $mobile['mobile'] : null,
            'mobile_normalized' => $mobile['mobile_normalized'] !== '' ? $mobile['mobile_normalized'] : null,
            'email' => $email,
            'date_of_birth' => $customerData['date_of_birth'] ?? null,
            'gender' => $customerData['gender'] ?? null,
            'is_business_customer' => $isBusinessCustomer,
            'company_name' => $isBusinessCustomer ? ($customerData['company_name'] ?? null) : null,
            'gst_number' => $isBusinessCustomer ? ($customerData['gst_number'] ?? null) : null,
        ];
    }

    private function findCustomerByUser(User $user): ?Customer
    {
        return Customer::query()
            ->where('user_id', $user->getKey())
            ->whereNull('deleted_at')
            ->first();
    }

    private function findCustomerByMobile(string $mobileNormalized): ?Customer
    {
        return Customer::query()
            ->where('mobile_normalized', $mobileNormalized)
            ->whereNull('deleted_at')
            ->first();
    }

    private function findCustomerByEmail(string $email): ?Customer
    {
        return Customer::query()
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->first();
    }

    private function findUserByMobile(string $mobileNormalized): ?User
    {
        $exact = User::query()
            ->where('mobile', $mobileNormalized)
            ->whereNull('deleted_at')
            ->first();

        if ($exact instanceof User) {
            return $exact;
        }

        return User::query()
            ->whereNotNull('mobile')
            ->whereNull('deleted_at')
            ->get()
            ->first(function (User $user) use ($mobileNormalized): bool {
                return $this->mobileNumberNormalizer->normalize((string) $user->mobile)['mobile_normalized'] === $mobileNormalized;
            });
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->first();
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function name(array $customerData, string $mobile): string
    {
        $name = trim((string) ($customerData['name'] ?? ''));

        return $name !== '' ? $name : 'Walk-in Customer - '.$mobile;
    }
}
