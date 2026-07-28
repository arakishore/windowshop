<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Models\TaxClass;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxSlabController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
    ) {
    }

    public function index(Request $request): View
    {
        $merchant = $this->activeMerchant($request);
        $merchant->loadMissing('businessAddress.country', 'businessAddress.state');

        $countryId = $merchant->businessAddress?->country_id;

        $taxClasses = TaxClass::query()
            ->active()
            ->with([
                'country',
                'rates' => fn ($query) => $query
                    ->active()
                    ->with('components')
                    ->orderBy('total_rate')
                    ->orderBy('effective_from')
                    ->orderBy('priority')
                    ->orderBy('id'),
            ])
            ->when($countryId, fn ($query, int $countryId) => $query->where('country_id', $countryId))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        return view('merchant.tax-slabs.index', [
            'merchant' => $merchant,
            'taxClasses' => $taxClasses,
        ]);
    }

    private function activeMerchant(Request $request): MerchantProfile
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);

        return $merchant;
    }
}
