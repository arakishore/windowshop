<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreMerchantCustomerRequest;
use App\Http\Requests\Merchant\UpdateMerchantCustomerRequest;
use App\Models\LocCountry;
use App\Models\MerchantCustomer;
use App\Models\MerchantCustomerAddress;
use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Services\Merchant\MerchantCustomerService;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly MerchantCustomerService $customerService,
    ) {
    }

    public function index(Request $request): View
    {
        $merchant = $this->activeMerchant($request);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status'),
        ];

        $customers = MerchantCustomer::query()
            ->where('merchant_id', $merchant->getKey())
            ->withCount('orders')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('mobile_normalized', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%");
                });
            })
            ->when(in_array($filters['status'], array_keys($this->statuses()), true), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('merchant.customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('merchant.customers.create', [
            'customer' => new MerchantCustomer(['status' => MerchantCustomer::STATUS_ACTIVE]),
            'statuses' => $this->statuses(),
            'genders' => $this->genders(),
            'mobileLookupUrl' => route('merchant.customers.mobile-lookup'),
            'countryCodes' => $this->countryCodes(),
            'defaultMobileCountryCode' => $this->defaultMobileCountryCode($request),
        ]);
    }

    public function store(StoreMerchantCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customerService->create($request->merchant(), $request->validated());

        return redirect()
            ->route('merchant.customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        $data = $request->validate([
            'action' => ['required', Rule::in(['mark_active', 'mark_inactive', 'delete'])],
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer'],
        ]);

        $count = match ($data['action']) {
            'mark_active' => $this->bulkStatus($merchant, $data['customer_ids'], MerchantCustomer::STATUS_ACTIVE),
            'mark_inactive' => $this->bulkStatus($merchant, $data['customer_ids'], MerchantCustomer::STATUS_INACTIVE),
            'delete' => $this->bulkDelete($merchant, $data['customer_ids']),
        };

        return back()->with('success', "{$count} customer(s) updated successfully.");
    }

    public function show(Request $request, MerchantCustomer $customer): View
    {
        $this->authorizeCustomer($request, $customer);
        $customer->load(['user']);
        $activeTab = in_array($request->query('tab'), ['details', 'addresses', 'orders'], true)
            ? (string) $request->query('tab')
            : 'details';

        $orders = $customer->orders()
            ->latest()
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString();
        $addresses = $customer->addresses()
            ->with(['country', 'state', 'city'])
            ->latest()
            ->get();

        $summary = [
            'orders_count' => $customer->orders()->count(),
            'total_spent' => (float) $customer->orders()->sum('grand_total'),
            'addresses_count' => $addresses->count(),
            'last_order_at' => $customer->orders()->latest()->value('created_at'),
        ];

        return view('merchant.customers.show', [
            'customer' => $customer,
            'orders' => $orders,
            'addresses' => $addresses,
            'summary' => $summary,
            'statuses' => $this->statuses(),
            'addressStatuses' => $this->addressStatuses(),
            'activeTab' => $activeTab,
        ]);
    }

    public function edit(Request $request, MerchantCustomer $customer): View
    {
        $this->authorizeCustomer($request, $customer);

        return view('merchant.customers.edit', [
            'customer' => $customer,
            'statuses' => $this->statuses(),
            'genders' => $this->genders(),
            'mobileLookupUrl' => route('merchant.customers.mobile-lookup', ['ignore' => $customer->getKey()]),
            'countryCodes' => $this->countryCodes(),
            'defaultMobileCountryCode' => $customer->mobile_country_code ?: $this->defaultMobileCountryCode($request),
        ]);
    }

    public function mobileLookup(Request $request, MobileNumberNormalizer $normalizer): JsonResponse
    {
        $merchant = $this->activeMerchant($request);
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:30'],
            'mobile_country_code' => ['nullable', 'string', 'max:10'],
            'ignore' => ['nullable', 'integer'],
        ]);
        $mobile = $normalizer->normalize(
            (string) $data['mobile'],
            isset($data['mobile_country_code']) ? (string) $data['mobile_country_code'] : null,
        );

        $customer = MerchantCustomer::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('mobile_normalized', $mobile['mobile_normalized'])
            ->when(isset($data['ignore']), fn ($query) => $query->whereKeyNot((int) $data['ignore']))
            ->first();

        return response()->json([
            'available' => ! $customer instanceof MerchantCustomer,
            'normalized' => $mobile,
            'customer' => $customer instanceof MerchantCustomer ? [
                'id' => $customer->getKey(),
                'name' => $customer->name,
                'customer_code' => $customer->customer_code,
                'mobile' => $customer->mobile,
                'show_url' => route('merchant.customers.show', $customer),
            ] : null,
        ]);
    }

    public function update(UpdateMerchantCustomerRequest $request, MerchantCustomer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);
        $this->customerService->update($customer, $request->validated());

        return redirect()
            ->route('merchant.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function activate(Request $request, MerchantCustomer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);
        $customer->forceFill(['status' => MerchantCustomer::STATUS_ACTIVE])->save();

        return back()->with('success', 'Customer activated successfully.');
    }

    public function deactivate(Request $request, MerchantCustomer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);
        $customer->forceFill(['status' => MerchantCustomer::STATUS_INACTIVE])->save();

        return back()->with('success', 'Customer deactivated successfully.');
    }

    public function destroy(Request $request, MerchantCustomer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);
        $customer->delete();

        return redirect()
            ->route('merchant.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    private function activeMerchant(Request $request): MerchantProfile
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);

        return $merchant;
    }

    private function authorizeCustomer(Request $request, MerchantCustomer $customer): MerchantProfile
    {
        $merchant = $this->activeMerchant($request);
        abort_unless((int) $customer->merchant_id === (int) $merchant->getKey(), 404);

        return $merchant;
    }

    /**
     * @param array<int, int|string> $customerIds
     */
    private function bulkStatus(MerchantProfile $merchant, array $customerIds, string $status): int
    {
        return MerchantCustomer::query()
            ->where('merchant_id', $merchant->getKey())
            ->whereIn('id', $customerIds)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, int|string> $customerIds
     */
    private function bulkDelete(MerchantProfile $merchant, array $customerIds): int
    {
        return DB::transaction(function () use ($merchant, $customerIds): int {
            $customers = MerchantCustomer::query()
                ->where('merchant_id', $merchant->getKey())
                ->whereIn('id', $customerIds)
                ->get();

            foreach ($customers as $customer) {
                $customer->delete();
            }

            return $customers->count();
        });
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    private function countryCodes(): array
    {
        $codes = LocCountry::query()
            ->where('status', true)
            ->whereNotNull('phonecode')
            ->orderByRaw("CASE WHEN iso2 = 'IN' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['name', 'phonecode'])
            ->map(function (LocCountry $country): array {
                $code = '+'.ltrim((string) $country->phonecode, '+');

                return [
                    'code' => $code,
                    'label' => "{$code} {$country->name}",
                ];
            })
            ->unique('code')
            ->values()
            ->all();

        return $codes !== [] ? $codes : [
            ['code' => '+91', 'label' => '+91 India'],
        ];
    }

    private function defaultMobileCountryCode(Request $request): string
    {
        $merchant = $this->activeMerchant($request);
        $shops = $this->shopContextService->activeShops($merchant);
        $shop = $this->shopContextService->resolveActiveShop($shops, $request->session()->get('active_shop_id'));

        if ($shop instanceof Shop && $shop->country_id !== null) {
            $phoneCode = LocCountry::query()
                ->whereKey($shop->country_id)
                ->value('phonecode');

            if ($phoneCode !== null && trim((string) $phoneCode) !== '') {
                return '+'.ltrim((string) $phoneCode, '+');
            }
        }

        return '+91';
    }

    /**
     * @return array<string, array{label: string, badge_class: string}>
     */
    private function statuses(): array
    {
        return config('admin.customer.statuses', [
            MerchantCustomer::STATUS_ACTIVE => ['label' => 'Active', 'badge_class' => 'bg-success'],
            MerchantCustomer::STATUS_INACTIVE => ['label' => 'Inactive', 'badge_class' => 'bg-light text-body border'],
        ]);
    }

    /**
     * @return array<string, array{label: string, badge_class: string}>
     */
    private function addressStatuses(): array
    {
        return config('admin.customer_address.statuses', [
            MerchantCustomerAddress::STATUS_ACTIVE => ['label' => 'Active', 'badge_class' => 'bg-success'],
            MerchantCustomerAddress::STATUS_INACTIVE => ['label' => 'Inactive', 'badge_class' => 'bg-light text-body border'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function genders(): array
    {
        return [
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
            'prefer_not_to_say' => 'Prefer not to say',
        ];
    }
}
