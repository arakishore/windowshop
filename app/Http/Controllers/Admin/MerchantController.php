<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMerchantRequest;
use App\Http\Requests\Admin\UpdateMerchantRequest;
use App\Http\Requests\Admin\UpsertMerchantAddressRequest;
use App\Models\MerchantProfile;
use App\Services\Merchant\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MerchantController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchantService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $request->query('status'),
            'verification_status' => $request->query('verification_status'),
        ];

        return view('admin.merchants.index', [
            'merchants' => $this->merchantService->list($filters),
            'filters' => $filters,
            'accountStatuses' => $this->merchantService->accountStatuses(),
            'verificationStatuses' => $this->merchantService->verificationStatuses(),
        ]);
    }

    public function create(): View
    {
        return view('admin.merchants.create', [
            'merchant' => null,
            'businessTypes' => $this->merchantService->businessTypes(),
            'accountStatuses' => $this->merchantService->accountStatuses(),
            'verificationStatuses' => $this->merchantService->verificationStatuses(),
        ]);
    }

    public function store(StoreMerchantRequest $request): RedirectResponse
    {
        $merchant = $this->merchantService->create($request->validated(), Auth::id());

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('success', 'Merchant created successfully.');
    }

    public function show(MerchantProfile $merchant): View
    {
        return $this->manage($merchant, 'overview');
    }

    public function edit(MerchantProfile $merchant): View
    {
        return view('admin.merchants.edit', [
            'merchant' => $this->merchantService->loadForEdit($merchant),
            'businessTypes' => $this->merchantService->businessTypes(),
            'accountStatuses' => $this->merchantService->accountStatuses(),
            'verificationStatuses' => $this->merchantService->verificationStatuses(),
            'accountStatusBadgeClasses' => $this->merchantService->accountStatusBadgeClasses(),
            'verificationStatusBadgeClasses' => $this->merchantService->verificationStatusBadgeClasses(),
        ]);
    }

    public function address(MerchantProfile $merchant): View
    {
        return $this->manage($merchant, 'address');
    }

    public function updateAddress(UpsertMerchantAddressRequest $request, MerchantProfile $merchant): RedirectResponse
    {
        $this->merchantService->upsertBusinessAddress($merchant, $request->validated(), Auth::id());

        return redirect()
            ->route('admin.merchants.address', $merchant)
            ->with('success', 'Business address saved successfully.');
    }

    public function addressStates(Request $request): JsonResponse
    {
        $countryId = (int) $request->query('country_id');

        return response()->json(
            $countryId ? $this->merchantService->activeStates($countryId) : [],
        );
    }

    public function addressCities(Request $request): JsonResponse
    {
        $countryId = (int) $request->query('country_id');
        $stateId = (int) $request->query('state_id');

        return response()->json(
            $countryId && $stateId ? $this->merchantService->citiesForState($countryId, $stateId) : [],
        );
    }

    public function shops(MerchantProfile $merchant): View
    {
        return $this->manage($merchant, 'shops');
    }

    private function manage(MerchantProfile $merchant, string $activeTab): View
    {
        $viewData = [
            'merchant' => $this->merchantService->loadForManage($merchant),
            'activeTab' => $activeTab,
            'businessTypes' => $this->merchantService->businessTypes(),
            'accountStatuses' => $this->merchantService->accountStatuses(),
            'verificationStatuses' => $this->merchantService->verificationStatuses(),
            'accountStatusBadgeClasses' => $this->merchantService->accountStatusBadgeClasses(),
            'verificationStatusBadgeClasses' => $this->merchantService->verificationStatusBadgeClasses(),
        ];

        if ($activeTab === 'address') {
            $viewData = [
                ...$viewData,
                ...$this->merchantService->addressFormData($viewData['merchant']),
            ];
        }

        return view('admin.merchants.manage', $viewData);
    }

    public function update(UpdateMerchantRequest $request, MerchantProfile $merchant): RedirectResponse
    {
        $this->merchantService->update($merchant, $request->validated(), Auth::id());

        return redirect()
            ->route('admin.merchants.edit', $merchant)
            ->with('success', 'Merchant updated successfully.');
    }

    public function destroy(MerchantProfile $merchant): RedirectResponse
    {
        $this->merchantService->delete($merchant, Auth::id());

        return redirect()
            ->route('admin.merchants.index')
            ->with('success', 'Merchant deleted successfully.');
    }
}
