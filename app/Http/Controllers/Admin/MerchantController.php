<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMerchantRequest;
use App\Http\Requests\Admin\UpdateMerchantRequest;
use App\Models\MerchantProfile;
use App\Models\MerchantVerification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $verificationStatus = $request->query('verification_status');

        $merchants = MerchantProfile::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('business_name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('gst_number', 'like', "%{$search}%")
                        ->orWhere('pan_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($verificationStatus, fn ($query) => $query->where('verification_status', $verificationStatus))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.merchants.index', [
            'merchants' => $merchants,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'verification_status' => $verificationStatus,
            ],
            'accountStatuses' => $this->accountStatuses(),
            'verificationStatuses' => $this->verificationStatuses(),
        ]);
    }

    public function create(): View
    {
        return view('admin.merchants.create', [
            'merchant' => null,
            'businessTypes' => $this->businessTypes(),
            'accountStatuses' => $this->accountStatuses(),
            'verificationStatuses' => $this->verificationStatuses(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreMerchantRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();
        $merchantRoleId = $this->merchantRoleId();

        $merchant = DB::transaction(function () use ($data, $actorId, $merchantRoleId): MerchantProfile {
            $user = new User();
            $user->forceFill([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'mobile' => $this->nullable($data['mobile'] ?? null),
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
            ])->save();

            $merchant = MerchantProfile::create([
                ...$this->merchantAttributes($data),
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'verified_at' => $data['verification_status'] === 'approved' ? now() : null,
                'verified_by' => $data['verification_status'] === 'approved' ? $actorId : null,
                'rejection_reason' => $data['verification_status'] === 'rejected'
                    ? $this->nullable($data['rejection_reason'] ?? null)
                    : null,
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

            if ($merchant->verification_status !== 'pending') {
                $this->recordVerification($merchant, null, $merchant->verification_status, 'Initial merchant verification status.');
            }

            return $merchant;
        });

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('success', 'Merchant created successfully.');
    }

    public function show(MerchantProfile $merchant): View
    {
        $merchant->load([
            'user',
            'verifications' => fn ($query) => $query->latest()->limit(10),
            'verifications.reviewer',
        ])->loadCount([
            'addresses',
            'documents',
            'bankAccounts',
            'verifications',
        ]);

        return view('admin.merchants.show', [
            'merchant' => $merchant,
        ]);
    }

    public function edit(MerchantProfile $merchant): View
    {
        $merchant->load('user');

        return view('admin.merchants.edit', [
            'merchant' => $merchant,
            'businessTypes' => $this->businessTypes(),
            'accountStatuses' => $this->accountStatuses(),
            'verificationStatuses' => $this->verificationStatuses(),
        ]);
    }

    public function update(UpdateMerchantRequest $request, MerchantProfile $merchant): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();
        $oldVerificationStatus = $merchant->verification_status;

        DB::transaction(function () use ($data, $merchant, $actorId, $oldVerificationStatus): void {
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

            $verificationAttributes = $this->verificationAttributes($data, $actorId, $oldVerificationStatus);

            $merchant->forceFill([
                ...$this->merchantAttributes($data),
                ...$verificationAttributes,
                'updated_by' => $actorId,
            ])->save();

            if ($oldVerificationStatus !== $merchant->verification_status) {
                $this->recordVerification(
                    $merchant,
                    $oldVerificationStatus,
                    $merchant->verification_status,
                    $this->nullable($data['admin_comment'] ?? null),
                );
            }
        });

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('success', 'Merchant updated successfully.');
    }

    public function destroy(MerchantProfile $merchant): RedirectResponse
    {
        DB::transaction(function () use ($merchant): void {
            $actorId = Auth::id();
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

        return redirect()
            ->route('admin.merchants.index')
            ->with('success', 'Merchant deleted successfully.');
    }

    /**
     * @throws ValidationException
     */
    private function merchantRoleId(): int
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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function merchantAttributes(array $data): array
    {
        return [
            'business_name' => $data['business_name'],
            'legal_name' => $this->nullable($data['legal_name'] ?? null),
            'business_type' => $this->nullable($data['business_type'] ?? null),
            'gst_number' => $this->nullable($data['gst_number'] ?? null),
            'pan_number' => $this->nullable($data['pan_number'] ?? null),
            'contact_person_name' => $this->nullable($data['contact_person_name'] ?? null),
            'contact_email' => $this->nullable($data['contact_email'] ?? null),
            'contact_mobile' => $this->nullable($data['contact_mobile'] ?? null),
            'alternate_mobile' => $this->nullable($data['alternate_mobile'] ?? null),
            'website_url' => $this->nullable($data['website_url'] ?? null),
            'verification_status' => $data['verification_status'],
            'status' => $data['status'],
            'admin_note' => $this->nullable($data['admin_note'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function verificationAttributes(array $data, ?int $actorId, ?string $oldStatus): array
    {
        if ($data['verification_status'] === 'approved') {
            return [
                'verified_at' => now(),
                'verified_by' => $actorId,
                'rejection_reason' => null,
            ];
        }

        return [
            'verified_at' => null,
            'verified_by' => null,
            'rejection_reason' => $data['verification_status'] === 'rejected'
                ? $this->nullable($data['rejection_reason'] ?? null)
                : null,
        ];
    }

    private function recordVerification(MerchantProfile $merchant, ?string $oldStatus, string $newStatus, ?string $comment): void
    {
        MerchantVerification::create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchant->getKey(),
            'verification_type' => 'profile',
            'related_type' => MerchantProfile::class,
            'related_id' => $merchant->getKey(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'admin_comment' => $comment,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function businessTypes(): array
    {
        return [
            'individual' => 'Individual',
            'proprietorship' => 'Proprietorship',
            'partnership' => 'Partnership',
            'llp' => 'LLP',
            'pvt_ltd' => 'Private Limited',
            'public_ltd' => 'Public Limited',
            'other' => 'Other',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function verificationStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function accountStatuses(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended',
        ];
    }
}
