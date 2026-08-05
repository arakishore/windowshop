<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreBannerRequest;
use App\Http\Requests\Merchant\UpdateBannerRequest;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Banner\BannerImageService;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly BannerImageService $images,
    ) {}

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'position' => $request->query('position', ''),
            'status' => $request->query('status', ''),
        ];

        $banners = Banner::withTrashed()
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->when($filters['search'] !== '', fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%'))
            ->when(BannerPosition::tryFrom($filters['position']) !== null, fn ($query) => $query->where('position', $filters['position']))
            ->when(in_array($filters['status'], [Banner::STATUS_ACTIVE, Banner::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status'])->whereNull('deleted_at'))
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed())
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('merchant.banners.index', [
            'banners' => $banners,
            'filters' => $filters,
            'positions' => BannerPosition::options(BannerPosition::SCOPE_MERCHANT),
            'activeShop' => $shop,
        ]);
    }

    public function create(Request $request): View
    {
        return view('merchant.banners.create', $this->formData(new Banner(['status' => Banner::STATUS_ACTIVE, 'link_type' => BannerLinkType::NONE]), $this->activeShop($request)));
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $banner = Banner::query()->create([
            ...$request->bannerData(),
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'desktop_image_path' => $this->images->store($request->file('desktop_image'), uniqid('', true), 'desktop'),
            'mobile_image_path' => $request->hasFile('mobile_image') ? $this->images->store($request->file('mobile_image'), uniqid('', true), 'mobile') : null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('merchant.banners.edit', $banner)->with('success', 'Banner created successfully.');
    }

    public function edit(Request $request, Banner $banner): View
    {
        $shop = $this->activeShop($request);
        $this->authorizeBanner($banner, $shop);
        abort_if($banner->trashed(), 404);

        return view('merchant.banners.edit', $this->formData($banner, $shop));
    }

    public function update(UpdateBannerRequest $request, Banner $banner): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $this->authorizeBanner($banner, $shop);
        abort_if($banner->trashed(), 404);
        $oldDesktop = $banner->desktop_image_path;
        $oldMobile = $banner->mobile_image_path;
        $data = [
            ...$request->bannerData(),
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'updated_by' => Auth::id(),
        ];

        if ($request->hasFile('desktop_image')) {
            $data['desktop_image_path'] = $this->images->store($request->file('desktop_image'), $banner, 'desktop');
        }

        if ($request->hasFile('mobile_image')) {
            $data['mobile_image_path'] = $this->images->store($request->file('mobile_image'), $banner, 'mobile');
        } elseif ($request->boolean('remove_mobile_image')) {
            $data['mobile_image_path'] = null;
        }

        $banner->forceFill($data)->save();

        if (($data['desktop_image_path'] ?? $oldDesktop) !== $oldDesktop) {
            $this->images->delete($oldDesktop);
        }

        if (array_key_exists('mobile_image_path', $data) && $data['mobile_image_path'] !== $oldMobile) {
            $this->images->delete($oldMobile);
        }

        return redirect()->route('merchant.banners.edit', $banner)->with('success', 'Banner updated successfully.');
    }

    public function destroy(Request $request, Banner $banner): RedirectResponse
    {
        $this->authorizeBanner($banner, $this->activeShop($request));
        abort_if($banner->trashed(), 404);

        $banner->forceFill(['deleted_by' => Auth::id(), 'updated_by' => Auth::id()])->save();
        $banner->delete();

        return redirect()->route('merchant.banners.index')->with('success', 'Banner deleted successfully.');
    }

    public function restore(Request $request, string $banner): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $banner = Banner::onlyTrashed()
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->where('uuid', $banner)
            ->firstOrFail();

        $banner->restore();
        $banner->forceFill(['deleted_by' => null, 'updated_by' => Auth::id()])->save();

        return redirect()->route('merchant.banners.edit', $banner)->with('success', 'Banner restored successfully.');
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

    private function authorizeBanner(Banner $banner, Shop $shop): void
    {
        abort_unless((int) $banner->merchant_id === (int) $shop->merchant_id && (int) $banner->shop_id === (int) $shop->getKey(), 404);
    }

    private function formData(Banner $banner, Shop $shop): array
    {
        return [
            'banner' => $banner,
            'positions' => BannerPosition::options(BannerPosition::SCOPE_MERCHANT),
            'positionMeta' => collect(BannerPosition::cases())->filter(fn (BannerPosition $position): bool => $position->isMerchant())->mapWithKeys(fn (BannerPosition $position): array => [$position->value => [
                'scope' => $position->scope(),
                'label' => $position->label(),
                'description' => $position->description(),
                'max' => $position->maxBanners(),
                'dimensions' => $position->recommendedDimensions(),
            ]])->all(),
            'linkTypes' => BannerLinkType::options(),
            'activeShop' => $shop,
            'linkTargets' => $this->linkTargets($shop),
        ];
    }

    private function linkTargets(Shop $shop): array
    {
        return [
            'product' => Product::query()
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at')
                ->orderBy('product_name')
                ->limit(500)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->getKey(),
                    'label' => $product->product_name,
                    'merchant_id' => $product->merchant_id,
                    'shop_id' => $product->shop_id,
                ])
                ->all(),
            'category' => ProductCategory::query()
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category): array => ['id' => $category->getKey(), 'label' => $category->name])
                ->all(),
            'brand' => Brand::query()
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get()
                ->map(fn (Brand $brand): array => ['id' => $brand->getKey(), 'label' => $brand->name])
                ->all(),
            'shop' => [[
                'id' => $shop->getKey(),
                'label' => $shop->name,
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
            ]],
        ];
    }
}
