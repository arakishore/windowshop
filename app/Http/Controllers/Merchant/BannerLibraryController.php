<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateCategory;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Shop;
use App\Services\Banner\BannerLimitService;
use App\Services\Banner\BannerTemplateLibraryService;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BannerLibraryController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly BannerTemplateLibraryService $library,
        private readonly BannerLimitService $limits,
    ) {}

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => $request->query('category', ''),
            'position' => $request->query('position', ''),
            'event' => $request->query('event', ''),
        ];

        $templates = $this->library->queryForMerchant($filters)
            ->paginate(18)
            ->withQueryString();

        $banners = Banner::query()
            ->with('bannerTemplate')
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->ordered()
            ->get();

        return view('merchant.banner-library.index', [
            'templates' => $templates,
            'filters' => $filters,
            'categories' => BannerTemplateCategory::options(),
            'positions' => BannerPosition::options(BannerPosition::SCOPE_MERCHANT),
            'activeShop' => $shop,
            'slotLimit' => $this->limits->limitPerShop(),
            'usedSlots' => $banners->count(),
            'banners' => $banners,
        ]);
    }

    private function activeShop(Request $request): Shop
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $shop = $this->shopContextService->resolveActiveShop(
            $this->shopContextService->activeShops($merchant),
            $request->session()->get('active_shop_id'),
        );
        abort_unless($shop !== null, 403);

        return $shop;
    }
}
