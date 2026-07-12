<?php

namespace App\Services\Merchant;

use App\Enums\MerchantBusinessType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantVerificationStatus;
use App\Models\MerchantAddress;
use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MerchantService
{
    /**
     * @param array{q: string, status: mixed, verification_status: mixed} $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = $filters['q'];
        $status = $filters['status'];
        $verificationStatus = $filters['verification_status'];

        return MerchantProfile::query()
            ->with(['user', 'businessAddress'])
            ->withCount('shops')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('business_name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('gst_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        })
                        ->orWhereHas('businessAddress', function ($query) use ($search): void {
                            $query
                                ->where('address_line_1', 'like', "%{$search}%")
                                ->orWhere('address_line_2', 'like', "%{$search}%")
                                ->orWhere('landmark', 'like', "%{$search}%")
                                ->orWhere('pincode', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($verificationStatus, fn ($query) => $query->where('verification_status', $verificationStatus))
            ->orderByDesc('created_at')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();
    }

    public function loadForEdit(MerchantProfile $merchant): MerchantProfile
    {
        return $merchant->load('user');
    }

    public function loadForManage(MerchantProfile $merchant): MerchantProfile
    {
        return $merchant->load(['user', 'verifiedBy', 'businessAddress']);
    }

    /**
     * @return array<string, mixed>
     */
    public function addressFormData(MerchantProfile $merchant): array
    {
        $address = $merchant->businessAddress;
        $defaultLocation = $this->defaultBusinessLocation();

        $countryId = (int) old('country_id', $address?->country_id ?? $defaultLocation['country_id']);
        $stateId = (int) old('state_id', $address?->state_id ?? $defaultLocation['state_id']);

        return [
            'address' => $address,
            'countries' => $this->activeCountries(),
            'states' => $countryId ? $this->activeStates($countryId) : collect(),
            'cities' => $countryId && $stateId ? $this->citiesForState($countryId, $stateId) : collect(),
            'defaultLocation' => $defaultLocation,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertBusinessAddress(MerchantProfile $merchant, array $data, ?int $actorId): MerchantAddress
    {
        return DB::transaction(function () use ($merchant, $data, $actorId): MerchantAddress {
            $address = MerchantAddress::query()
                ->where('merchant_id', $merchant->getKey())
                ->where('address_type', 'business')
                ->firstOrNew([
                    'merchant_id' => $merchant->getKey(),
                    'address_type' => 'business',
                ]);

            if (! $address->exists) {
                $address->created_by = $actorId;
            }

            $address->forceFill([
                'address_line_1' => $this->nullable($data['address_line_1']) ?? '',
                'address_line_2' => $this->nullable($data['address_line_2'] ?? null),
                'landmark' => $this->nullable($data['landmark'] ?? null),
                'country_id' => (int) $data['country_id'],
                'state_id' => (int) $data['state_id'],
                'city_id' => (int) $data['city_id'],
                'pincode' => $this->nullable($data['pincode']) ?? '',
                'status' => 'active',
                'updated_by' => $actorId,
                'deleted_by' => null,
            ])->save();

            return $address;
        });
    }

    /**
     * @return Collection<int, object>
     */
    public function activeCountries(): Collection
    {
        return DB::table('loc_countries')
            ->select('id', 'name')
            ->where('status', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function activeStates(int $countryId): Collection
    {
        return DB::table('loc_states')
            ->select('id', 'name')
            ->where('country_id', $countryId)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function citiesForState(int $countryId, int $stateId): Collection
    {
        return DB::table('loc_cities')
            ->select('id', 'name')
            ->where('country_id', $countryId)
            ->where('state_id', $stateId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{country_id: ?int, state_id: ?int, city_id: ?int}
     */
    public function defaultBusinessLocation(): array
    {
        $countryId = DB::table('loc_countries')
            ->where('iso2', 'IN')
            ->whereNull('deleted_at')
            ->value('id');

        $stateId = $countryId
            ? DB::table('loc_states')
                ->where('country_id', $countryId)
                ->where('iso2', 'MH')
                ->whereNull('deleted_at')
                ->value('id')
            : null;

        $cityId = $countryId && $stateId
            ? DB::table('loc_cities')
                ->where('country_id', $countryId)
                ->where('state_id', $stateId)
                ->where('name', 'Nashik')
                ->whereNull('deleted_at')
                ->value('id')
            : null;

        return [
            'country_id' => $countryId ? (int) $countryId : null,
            'state_id' => $stateId ? (int) $stateId : null,
            'city_id' => $cityId ? (int) $cityId : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function create(array $data, ?int $actorId): MerchantProfile
    {
        $merchantRoleId = $this->merchantRoleId();

        return DB::transaction(function () use ($data, $actorId, $merchantRoleId): MerchantProfile {
            $user = new User();
            $user->forceFill([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'mobile' => $this->nullable($data['mobile'] ?? null),
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
            ])->save();

            $merchant = MerchantProfile::create([
                ...$this->merchantAttributes($data),
                ...$this->verificationAttributes($data, $actorId),
                'user_id' => $user->getKey(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            DB::table('auth_user_roles')->updateOrInsert(
                [
                    'user_id' => $user->getKey(),
                    'role_id' => $merchantRoleId,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            return $merchant;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(MerchantProfile $merchant, array $data, ?int $actorId): void
    {
        DB::transaction(function () use ($merchant, $data, $actorId): void {
            $merchant->load('user');

            $userAttributes = [
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'mobile' => $this->nullable($data['mobile'] ?? null),
                'status' => $data['status'],
            ];

            if (! empty($data['password'])) {
                $userAttributes['password'] = Hash::make($data['password']);
            }

            $merchant->user->forceFill($userAttributes)->save();

            $merchant->forceFill([
                ...$this->merchantAttributes($data),
                ...$this->verificationAttributes($data, $actorId),
                'updated_by' => $actorId,
            ])->save();

            // Future: generic audit/activity logging will be implemented application-wide.
        });
    }

    public function delete(MerchantProfile $merchant, ?int $actorId): void
    {
        DB::transaction(function () use ($merchant, $actorId): void {
            $merchant->load('user');

            $merchant->forceFill([
                'status' => 'deleted',
                'deleted_by' => $actorId,
            ])->save();
            $merchant->delete();

            $merchant->user->forceFill([
                'status' => 'deleted',
            ])->save();
            $merchant->user->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    public function merchantRoleId(): int
    {
        $roleId = DB::table('auth_roles')
            ->where('slug', 'merchant')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('id');

        if ($roleId === null) {
            throw ValidationException::withMessages([
                'role' => 'The active merchant role must exist before creating merchants.',
            ]);
        }

        return (int) $roleId;
    }

    /**
     * @return array<string, string>
     */
    public function businessTypes(): array
    {
        return MerchantBusinessType::options();
    }

    /**
     * @return array<string, string>
     */
    public function verificationStatuses(): array
    {
        return MerchantVerificationStatus::options();
    }

    /**
     * @return array<string, string>
     */
    public function accountStatuses(): array
    {
        return MerchantStatus::options();
    }

    /**
     * @return array<string, string>
     */
    public function accountStatusBadgeClasses(): array
    {
        return [
            MerchantStatus::ACTIVE->value => 'bg-success',
            MerchantStatus::INACTIVE->value => 'bg-light text-body border',
            MerchantStatus::SUSPENDED->value => 'bg-warning',
            'deleted' => 'bg-danger',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function verificationStatusBadgeClasses(): array
    {
        return [
            MerchantVerificationStatus::PENDING->value => 'bg-light text-body border',
            MerchantVerificationStatus::SUBMITTED->value => 'bg-info',
            MerchantVerificationStatus::APPROVED->value => 'bg-success',
            MerchantVerificationStatus::REJECTED->value => 'bg-danger',
            MerchantVerificationStatus::SUSPENDED->value => 'bg-warning',
            'verified' => 'bg-success',
            'unverified' => 'bg-light text-body border',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function merchantAttributes(array $data): array
    {
        $attributes = [
            'business_name' => $data['business_name'],
            'legal_name' => $this->nullable($data['legal_name'] ?? null),
            'business_type' => $this->nullable($data['business_type'] ?? null),
            'gst_number' => $this->nullable($data['gst_number'] ?? null),
            'has_shop_license' => $this->nullableBool($data['has_shop_license'] ?? null),
            'has_fssai' => $this->nullableBool($data['has_fssai'] ?? null),
            'verification_status' => $data['verification_status'],
            'status' => $data['status'],
            'admin_note' => $this->nullable($data['admin_note'] ?? null),
        ];

        foreach (['contact_person_name', 'contact_email', 'contact_mobile', 'alternate_mobile'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $this->nullable($data[$field]);
            }
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function verificationAttributes(array $data, ?int $actorId): array
    {
        if ($data['verification_status'] === MerchantVerificationStatus::APPROVED->value) {
            return [
                'verified_at' => now(),
                'verified_by' => $actorId,
                'rejection_reason' => null,
            ];
        }

        if ($data['verification_status'] === MerchantVerificationStatus::REJECTED->value) {
            return [
                'verified_at' => null,
                'verified_by' => null,
                'rejection_reason' => $this->nullable($data['rejection_reason'] ?? null),
            ];
        }

        return [
            'verified_at' => null,
            'verified_by' => null,
            'rejection_reason' => null,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
