<?php

namespace App\Services\Customer;

use App\Enums\UserRegistrationSource;
use App\Models\User;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerIdentityResolver
{
    public function __construct(
        private readonly MobileNumberNormalizer $mobileNumberNormalizer,
    ) {
    }

    public function resolveOrCreateForPos(array $customerData): User
    {
        $mobile = $this->mobileNumberNormalizer->normalize(
            (string) ($customerData['mobile'] ?? ''),
            isset($customerData['mobile_country_code']) ? (string) $customerData['mobile_country_code'] : null,
        );
        $email = $this->normalizeEmail($customerData['email'] ?? null);

        $existingUser = $this->findByMobile($mobile['mobile_normalized'])
            ?? ($email !== null ? $this->findByEmail($email) : null);

        if ($existingUser instanceof User) {
            return $existingUser;
        }

        $user = new User();
        $user->forceFill([
            'name' => $this->name($customerData, $mobile['mobile']),
            'email' => $email,
            'mobile' => $mobile['mobile'],
            'password' => Hash::make(Str::random(64)),
            'status' => 'active',
            'registration_source' => UserRegistrationSource::POS->value,
        ])->save();

        return $user;
    }

    private function findByMobile(string $mobileNormalized): ?User
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

    private function findByEmail(string $email): ?User
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
