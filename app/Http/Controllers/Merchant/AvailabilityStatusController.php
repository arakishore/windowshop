<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\ProductAvailabilityStatus;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AvailabilityStatusController extends Controller
{
    public function __construct(private readonly MerchantShopContextService $shopContextService)
    {
    }

    public function index(Request $request): View
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status', ''),
        ];

        $query = ProductAvailabilityStatus::withTrashed()
            ->where('merchant_id', $merchant->getKey())
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_description', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] === ProductAvailabilityStatus::STATUS_ACTIVE, fn ($query) => $query->where('status', ProductAvailabilityStatus::STATUS_ACTIVE)->whereNull('deleted_at'))
            ->when($filters['status'] === ProductAvailabilityStatus::STATUS_INACTIVE, fn ($query) => $query->where('status', ProductAvailabilityStatus::STATUS_INACTIVE)->whereNull('deleted_at'))
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed());

        $statuses = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selected = $request->query('status_row')
            ? ProductAvailabilityStatus::withTrashed()
                ->where('merchant_id', $merchant->getKey())
                ->where('uuid', $request->query('status_row'))
                ->first()
            : $statuses->first();

        return view('merchant.availability-statuses.index', [
            'merchant' => $merchant,
            'statuses' => $statuses,
            'selectedStatus' => $selected,
            'filters' => $filters,
            'badgeTypes' => ProductAvailabilityStatus::badgeTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);
        $data = $this->validated($request, (int) $merchant->getKey());

        ProductAvailabilityStatus::query()->create([
            ...$data,
            'merchant_id' => $merchant->getKey(),
            'code' => $this->uniqueCode((int) $merchant->getKey(), $data['name']),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('merchant.availability-statuses.index')
            ->with('success', 'Availability status created successfully.');
    }

    public function update(Request $request, ProductAvailabilityStatus $availabilityStatus): RedirectResponse
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null && (int) $availabilityStatus->merchant_id === (int) $merchant->getKey(), 404);
        abort_if($availabilityStatus->trashed(), 404);
        $data = $this->validated($request, (int) $merchant->getKey(), $availabilityStatus);

        $availabilityStatus->forceFill([
            ...$data,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.availability-statuses.index', ['status_row' => $availabilityStatus->getRouteKey()])
            ->with('success', 'Availability status updated successfully.');
    }

    public function destroy(Request $request, ProductAvailabilityStatus $availabilityStatus): RedirectResponse
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null && (int) $availabilityStatus->merchant_id === (int) $merchant->getKey(), 404);
        abort_if($availabilityStatus->trashed(), 404);

        if ($availabilityStatus->products()->exists() || $availabilityStatus->variants()->exists()) {
            throw ValidationException::withMessages([
                'availability_status' => 'This availability status is assigned to products or variants and cannot be deleted.',
            ]);
        }

        $availabilityStatus->forceFill([
            'deleted_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ])->save();
        $availabilityStatus->delete();

        return redirect()
            ->route('merchant.availability-statuses.index')
            ->with('success', 'Availability status deleted successfully.');
    }

    public function restore(Request $request, string $availabilityStatus): RedirectResponse
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $status = ProductAvailabilityStatus::onlyTrashed()
            ->where('merchant_id', $merchant->getKey())
            ->where('uuid', $availabilityStatus)
            ->firstOrFail();

        $status->restore();
        $status->forceFill([
            'deleted_by' => null,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.availability-statuses.index', ['status_row' => $status->getRouteKey()])
            ->with('success', 'Availability status restored successfully.');
    }

    /**
     * @return array{name: string, customer_description: ?string, purchase_allowed: bool, badge_type: string, sort_order: int, status: string}
     */
    private function validated(Request $request, int $merchantId, ?ProductAvailabilityStatus $status = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'customer_description' => ['nullable', 'string', 'max:1000'],
            'purchase_allowed' => ['nullable', 'boolean'],
            'badge_type' => ['required', Rule::in(ProductAvailabilityStatus::badgeTypes())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'status' => ['nullable', Rule::in([ProductAvailabilityStatus::STATUS_ACTIVE, ProductAvailabilityStatus::STATUS_INACTIVE])],
        ]);

        return [
            'name' => trim($data['name']),
            'customer_description' => $this->nullable($data['customer_description'] ?? null),
            'purchase_allowed' => (bool) ($data['purchase_allowed'] ?? false),
            'badge_type' => $data['badge_type'],
            'sort_order' => (int) $data['sort_order'],
            'status' => $data['status'] ?? ProductAvailabilityStatus::STATUS_INACTIVE,
        ];
    }

    private function uniqueCode(int $merchantId, string $name): string
    {
        $base = Str::upper(Str::slug($name, '_')) ?: 'CUSTOM';
        $code = $base;
        $suffix = 2;

        while (ProductAvailabilityStatus::withTrashed()->where('merchant_id', $merchantId)->where('code', $code)->exists()) {
            $code = "{$base}_{$suffix}";
            $suffix++;
        }

        return $code;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
