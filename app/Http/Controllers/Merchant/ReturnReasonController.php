<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\ReturnReason;
use App\Services\Merchant\MerchantReturnReasonInitializer;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnReasonController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly MerchantReturnReasonInitializer $returnReasonInitializer,
    )
    {
    }

    public function index(Request $request): View
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $search = trim((string) $request->query('search', ''));
        $reasons = ReturnReason::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('code', '!=', 'exchange')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $selected = $request->query('reason')
            ? ReturnReason::query()->where('merchant_id', $merchant->getKey())->where('code', '!=', 'exchange')->where('uuid', $request->query('reason'))->first()
            : $reasons->first();

        return view('merchant.return-reasons.index', [
            'merchant' => $merchant,
            'reasons' => $reasons,
            'selectedReason' => $selected,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);
        $data = $this->validated($request);

        ReturnReason::query()->create([
            ...$data,
            'merchant_id' => $merchant->getKey(),
            'code' => $this->returnReasonInitializer->uniqueCode((int) $merchant->getKey(), $data['name']),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('merchant.return-reasons.index')->with('success', 'Return reason created successfully.');
    }

    public function update(Request $request, ReturnReason $returnReason): RedirectResponse
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null && (int) $returnReason->merchant_id === (int) $merchant->getKey(), 404);
        $data = $this->validated($request);

        $returnReason->forceFill([
            ...$data,
            'code' => $returnReason->code ?: $this->returnReasonInitializer->uniqueCode((int) $merchant->getKey(), $data['name'], $returnReason),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.return-reasons.index', ['reason' => $returnReason->getRouteKey()])
            ->with('success', 'Return reason updated successfully.');
    }

    public function destroy(Request $request, ReturnReason $returnReason): RedirectResponse
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null && (int) $returnReason->merchant_id === (int) $merchant->getKey(), 404);
        $returnReason->delete();

        return redirect()->route('merchant.return-reasons.index')->with('success', 'Return reason deleted successfully.');
    }

    /**
     * @return array{name: string, sort_order: int, restock_by_default: bool, requires_manager_override: bool, status: string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'restock_by_default' => ['nullable', 'boolean'],
            'requires_manager_override' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in([ReturnReason::STATUS_ACTIVE, ReturnReason::STATUS_INACTIVE])],
        ]);

        return [
            'name' => $data['name'],
            'sort_order' => (int) $data['sort_order'],
            'restock_by_default' => (bool) ($data['restock_by_default'] ?? false),
            'requires_manager_override' => (bool) ($data['requires_manager_override'] ?? false),
            'status' => $data['status'] ?? ReturnReason::STATUS_INACTIVE,
        ];
    }

}
