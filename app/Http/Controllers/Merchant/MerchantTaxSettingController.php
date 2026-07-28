<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\MerchantTaxSettingRequest;
use App\Models\MerchantProfile;
use App\Models\MerchantTaxSetting;
use App\Models\TaxClass;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Merchant\MerchantTaxSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantTaxSettingController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly MerchantTaxSettingService $taxSettings,
    ) {
    }

    public function edit(Request $request): View
    {
        $merchant = $this->activeMerchant($request);
        $setting = $this->taxSettings->firstOrDefault($merchant);
        $businessAddress = $merchant->businessAddress()->first();
        $countryId = $businessAddress?->country_id;

        return view('merchant.tax-settings.edit', [
            'merchant' => $merchant->loadMissing('businessAddress'),
            'setting' => $setting,
            'taxClasses' => TaxClass::query()
                ->active()
                ->with('country')
                ->when($countryId, fn ($query, int $countryId) => $query->where('country_id', $countryId))
                ->orderBy('country_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(MerchantTaxSettingRequest $request): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        $this->taxSettings->save($merchant, $request->validated());

        return back()->with('success', 'Tax settings updated successfully.');
    }

    public function destroy(Request $request, MerchantTaxSetting $taxSetting): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        abort_unless((int) $taxSetting->merchant_id === (int) $merchant->getKey(), 404);

        $this->taxSettings->delete($taxSetting);

        return redirect()->route('merchant.tax-settings.edit')->with('success', 'Tax settings removed successfully.');
    }

    private function activeMerchant(Request $request): MerchantProfile
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);

        return $merchant;
    }
}
