<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UpdateMerchantShopRequest;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Merchant\MerchantShopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantShopController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly MerchantShopService $shopService,
    ) {
    }

    public function index(Request $request): View
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());

        abort_unless($merchant !== null, 403);

        return view('merchant.shops.index', [
            ...$this->viewData($request),
            'shops' => $this->shopService->shopsForMerchant($merchant),
        ]);
    }

    public function show(Request $request, Shop $shop): View
    {
        $this->authorizeShop($request, $shop);

        return view('merchant.shops.show', [
            ...$this->viewData($request),
            'shop' => $shop->load(['merchant', 'rootProductCategory', 'audiences', 'country', 'state', 'city']),
        ]);
    }

    public function edit(Request $request, Shop $shop): View
    {
        $this->authorizeShop($request, $shop);

        return view('merchant.shops.edit', [
            ...$this->viewData($request),
            ...$this->shopService->formData($shop),
            'shop' => $shop->load(['merchant', 'rootProductCategory', 'audiences', 'country', 'state', 'city']),
        ]);
    }

    public function update(UpdateMerchantShopRequest $request, Shop $shop): RedirectResponse
    {
        $merchant = $this->authorizeShop($request, $shop);
        $wasWorkingShop = (int) $request->session()->get('active_shop_id') === (int) $shop->getKey();

        $this->shopService->updateShop($shop, $merchant, $request->validated(), $request, $request->user()?->getKey());

        $shop->refresh()->load('city');

        if ($wasWorkingShop && $shop->status === 'inactive') {
            $request->session()->forget('active_shop_id');
            $request->session()->forget('active_shop_name');

            $nextActiveShop = $this->shopContextService->activeShops($merchant)->first();

            if ($nextActiveShop instanceof Shop) {
                $request->session()->put('merchant_id', $merchant->getKey());
                $request->session()->put('active_role_id', $this->shopContextService->merchantRoleId());
                $request->session()->put('active_shop_id', $nextActiveShop->getKey());
                $request->session()->put('active_shop_name', $this->shopContextService->label($nextActiveShop));

                return redirect()
                    ->route('merchant.shops.edit', $shop)
                    ->with('success', 'Shop updated successfully. Now managing "'.$this->shopContextService->label($nextActiveShop).'".');
            }

            return redirect()
                ->route('merchant.shops.index')
                ->with('warning', 'This shop is now inactive. No other active shop is available.');
        }

        if ($wasWorkingShop) {
            $request->session()->put('active_shop_name', $this->shopContextService->label($shop));
        }

        return redirect()
            ->route('merchant.shops.edit', $shop)
            ->with('success', 'Shop updated successfully.');
    }

    public function activate(Request $request, Shop $shop): RedirectResponse
    {
        $merchant = $this->authorizeShop($request, $shop);

        abort_unless($this->shopService->canBeActive($shop, $merchant), 422, 'This shop cannot be selected as active.');

        $shop->load('city');

        $request->session()->put('merchant_id', $merchant->getKey());
        $request->session()->put('active_role_id', $this->shopContextService->merchantRoleId());
        $request->session()->put('active_shop_id', $shop->getKey());
        $request->session()->put('active_shop_name', $this->shopContextService->label($shop));

        return back()->with('success', 'Now managing "'.$this->shopContextService->label($shop).'".');
    }

    private function authorizeShop(Request $request, Shop $shop): \App\Models\MerchantProfile
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());

        abort_unless($merchant !== null, 403);
        abort_unless((int) $shop->merchant_id === (int) $merchant->getKey(), 404);

        return $merchant;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Request $request): array
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());

        abort_unless($merchant !== null, 403);

        $activeShops = $this->shopContextService->activeShops($merchant);
        $activeShop = $this->shopContextService->resolveActiveShop(
            $activeShops,
            $request->session()->get('active_shop_id'),
        );

        return [
            'merchant' => $merchant,
            'shopStatuses' => config('admin.shop.statuses', []),
            'merchantActiveShopContext' => [
                'merchant' => $merchant,
                'shops' => $activeShops,
                'activeShop' => $activeShop,
                'activeShopLabel' => $this->shopContextService->label($activeShop),
            ],
        ];
    }
}
