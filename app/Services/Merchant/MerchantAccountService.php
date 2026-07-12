<?php

namespace App\Services\Merchant;

use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MerchantAccountService
{
    public function merchantForUser(User $user): MerchantProfile
    {
        $merchant = $user->merchantProfile()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        abort_unless($merchant !== null, 403);

        return $merchant;
    }

    /**
     * @param array{name: string, email: string, mobile: string} $data
     */
    public function updateProfile(User $user, MerchantProfile $merchant, array $data): void
    {
        abort_unless((int) $merchant->user_id === (int) $user->getKey(), 403);

        DB::transaction(function () use ($user, $merchant, $data): void {
            $user->forceFill([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'mobile' => $this->normalizeMobile($data['mobile']),
            ])->save();

            $merchant->forceFill([
                'updated_by' => $user->getKey(),
            ])->save();
        });
    }

    /**
     * @param array{current_password: string, password: string} $data
     *
     * @throws ValidationException
     */
    public function updatePassword(User $user, array $data): void
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The new password must be different from the current password.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateDetails(User $user, MerchantProfile $merchant, array $data): void
    {
        abort_unless((int) $merchant->user_id === (int) $user->getKey(), 403);

        DB::transaction(function () use ($user, $merchant, $data): void {
            $attributes = [
                'business_name' => $this->nullable($data['business_name']) ?? '',
                'contact_person_name' => $this->nullable($data['contact_person_name'] ?? null),
                'contact_email' => $this->nullableLower($data['contact_email'] ?? null),
                'contact_mobile' => $this->nullable($data['contact_mobile'] ?? null),
                'updated_by' => $user->getKey(),
            ];

            foreach (['legal_name', 'business_type', 'gst_number', 'has_shop_license', 'has_fssai'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = match ($field) {
                        'gst_number' => $this->nullableUpper($data[$field]),
                        'has_shop_license', 'has_fssai' => $this->nullableBool($data[$field]),
                        default => $this->nullable($data[$field]),
                    };
                }
            }

            $merchant->forceFill($attributes)->save();
        });
    }

    private function normalizeMobile(string $mobile): string
    {
        return trim($mobile);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableLower(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : Str::lower($value);
    }

    private function nullableUpper(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : Str::upper($value);
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
