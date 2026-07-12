<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MerchantShopContextController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_id' => ['required', 'integer'],
        ]);

        $merchant = $this->shopContextService->activeMerchantForUser($request->user());

        abort_unless($merchant !== null, 403);

        $shop = $this->shopContextService
            ->activeShops($merchant)
            ->firstWhere('id', (int) $data['shop_id']);

        if ($shop === null) {
            throw ValidationException::withMessages([
                'shop_id' => 'The selected shop is not available.',
            ]);
        }

        $request->session()->put('merchant_id', $merchant->getKey());
        $request->session()->put('active_role_id', $this->shopContextService->merchantRoleId());
        $request->session()->put('active_shop_id', $shop->getKey());
        $request->session()->put('active_shop_name', $this->shopContextService->label($shop));

        return back()->with('success', 'Now managing "'.$this->shopContextService->label($shop).'".');
    }
}
