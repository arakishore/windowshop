<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Enums\BannerSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreBannerRequest;
use App\Http\Requests\Merchant\UpdateBannerRequest;
use App\Models\Banner;
use App\Models\BannerTemplate;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Banner\BannerImageService;
use App\Services\Banner\BannerLimitService;
use App\Services\Banner\BannerTemplateActivationService;
use App\Services\Banner\BannerTemplateLibraryService;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly BannerImageService $images,
        private readonly BannerTemplateLibraryService $library,
        private readonly BannerTemplateActivationService $activation,
        private readonly BannerLimitService $limits,
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
            ->with('bannerTemplate')
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
        $banner = new Banner(['status' => Banner::STATUS_ACTIVE, 'link_type' => BannerLinkType::NONE]);
        $templateUuid = (string) $request->query('template', '');

        if ($templateUuid !== '') {
            $template = $this->library->findUsableForMerchant($templateUuid);
            $banner = $this->bannerFromTemplateDefaults($template, Banner::STATUS_INACTIVE);
        }

        return view('merchant.banners.create', $this->formData($banner, $this->activeShop($request)));
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        if ($request->input('source_type') === BannerSourceType::TEMPLATE->value) {
            $template = $this->library->findUsableForMerchant((string) $request->input('banner_template_uuid'));
            $banner = $this->activation->createMerchantBannerFromTemplate($template, $merchant, $shop, $request->bannerData(), $request->user());

            return redirect()->route('merchant.banners.edit', $banner)->with('success', 'Banner created from template successfully.');
        }

        $desktopPath = $this->images->store($request->file('desktop_image'), uniqid('', true), 'desktop');
        $mobilePath = $request->hasFile('mobile_image') ? $this->images->store($request->file('mobile_image'), uniqid('', true), 'mobile') : null;

        try {
            $banner = DB::transaction(function () use ($request, $merchant, $shop, $desktopPath, $mobilePath): Banner {
                if ($this->limits->usedSlots($merchant, $shop, true) >= $this->limits->limitPerShop()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'desktop_image' => 'This shop has reached its maximum of '.$this->limits->limitPerShop().' banner slots. Edit or replace one of the existing banners.',
                    ]);
                }

                return Banner::query()->create([
                    ...Arr::except($request->bannerData(), ['banner_template_uuid']),
                    'merchant_id' => $shop->merchant_id,
                    'shop_id' => $shop->getKey(),
                    'source_type' => BannerSourceType::CUSTOM_UPLOAD->value,
                    'banner_template_id' => null,
                    'desktop_image_path' => $desktopPath,
                    'mobile_image_path' => $mobilePath,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            });
        } catch (\Throwable $throwable) {
            $this->images->delete($desktopPath);
            $this->images->delete($mobilePath);

            throw $throwable;
        }

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
        $oldWasCustom = $banner->usesCustomUpload();
        $data = [
            ...Arr::except($request->bannerData(), ['banner_template_uuid']),
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'updated_by' => Auth::id(),
        ];

        if ($request->input('source_type') === BannerSourceType::CUSTOM_UPLOAD->value) {
            if ($banner->usesTemplate() && ! $request->hasFile('desktop_image')) {
                return back()->withErrors(['desktop_image' => 'Upload a desktop image when switching a template banner to custom upload.'])->withInput();
            }

            $data['source_type'] = BannerSourceType::CUSTOM_UPLOAD->value;
            $data['banner_template_id'] = null;
        }

        if ($request->hasFile('desktop_image')) {
            $data['desktop_image_path'] = $this->images->store($request->file('desktop_image'), $banner, 'desktop');
        }

        if ($request->hasFile('mobile_image')) {
            $data['mobile_image_path'] = $this->images->store($request->file('mobile_image'), $banner, 'mobile');
        } elseif ($request->boolean('remove_mobile_image')) {
            $data['mobile_image_path'] = null;
        }

        $banner->forceFill($data)->save();

        if (($data['desktop_image_path'] ?? $oldDesktop) !== $oldDesktop && $oldWasCustom) {
            $this->images->deleteOwnedCustom($oldDesktop, $banner);
        }

        if (array_key_exists('mobile_image_path', $data) && $data['mobile_image_path'] !== $oldMobile && $oldWasCustom) {
            $this->images->deleteOwnedCustom($oldMobile, $banner);
        }

        return redirect()->route('merchant.banners.edit', $banner)->with('success', 'Banner updated successfully.');
    }

    public function replaceTemplate(Request $request, Banner $banner): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $this->authorizeBanner($banner, $shop);
        abort_if($banner->trashed(), 404);

        $data = $request->validate([
            'banner_template_uuid' => ['required', 'string', 'exists:banner_templates,uuid'],
            'apply_template_defaults' => ['required', 'in:images_only,text,all'],
        ]);

        $template = $this->library->findUsableForMerchant($data['banner_template_uuid']);
        $this->activation->replaceBannerTemplate($banner, $template, $data, $request->user());

        return redirect()->route('merchant.banners.edit', $banner)->with('success', 'Banner template replaced successfully.');
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
            'bannerTemplates' => $this->library->getForMerchant(),
            'slotLimit' => $this->limits->limitPerShop(),
            'usedSlots' => $this->limits->usedSlots($shop->merchant, $shop),
            'linkTargets' => $this->linkTargets($shop),
        ];
    }

    private function bannerFromTemplateDefaults(BannerTemplate $template, string $status): Banner
    {
        $schedule = $this->activation->recommendedSchedule($template);

        return new Banner([
            'source_type' => BannerSourceType::TEMPLATE,
            'banner_template_id' => $template->getKey(),
            'position' => $template->default_position,
            'title' => $template->default_title,
            'subtitle' => $template->default_subtitle,
            'description' => $template->description,
            'button_text' => $template->default_button_text,
            'desktop_image_path' => $template->desktop_image_path,
            'mobile_image_path' => $template->mobile_image_path,
            'sort_order' => $template->sort_order,
            'starts_at' => $schedule['starts_at'],
            'ends_at' => $schedule['ends_at'],
            'status' => $status,
            'link_type' => BannerLinkType::NONE,
        ]);
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
