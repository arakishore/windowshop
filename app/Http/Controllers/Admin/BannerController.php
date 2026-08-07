<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Enums\BannerSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Models\Banner;
use App\Models\BannerTemplate;
use App\Models\Brand;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Banner\BannerImageService;
use App\Services\Banner\BannerTemplateActivationService;
use App\Services\Banner\BannerTemplateLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function __construct(
        private readonly BannerImageService $images,
        private readonly BannerTemplateLibraryService $library,
        private readonly BannerTemplateActivationService $activation,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'position' => $request->query('position', ''),
            'status' => $request->query('status', ''),
            'owner_type' => $request->query('owner_type', ''),
        ];

        $banners = Banner::withTrashed()
            ->with(['merchant', 'shop', 'bannerTemplate'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhere('subtitle', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when(BannerPosition::tryFrom($filters['position']) !== null, fn ($query) => $query->where('position', $filters['position']))
            ->when(in_array($filters['status'], [Banner::STATUS_ACTIVE, Banner::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status'])->whereNull('deleted_at'))
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed())
            ->when($filters['owner_type'] === 'marketplace', fn ($query) => $query->forMarketplace())
            ->when($filters['owner_type'] === 'merchant', fn ($query) => $query->whereNotNull('merchant_id'))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.banners.index', [
            'banners' => $banners,
            'filters' => $filters,
            'positions' => BannerPosition::options(),
            'statusOptions' => [Banner::STATUS_ACTIVE => 'Active', Banner::STATUS_INACTIVE => 'Inactive', 'trash' => 'Trash'],
        ]);
    }

    public function create(Request $request): View
    {
        $banner = new Banner(['status' => Banner::STATUS_ACTIVE, 'link_type' => BannerLinkType::NONE]);
        $templateUuid = (string) $request->query('template', '');

        if ($templateUuid !== '') {
            $template = $this->library->findUsableForAdmin($templateUuid);
            $banner = $this->bannerFromTemplateDefaults($template, Banner::STATUS_INACTIVE);
        }

        return view('admin.banners.create', $this->formData($banner));
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        if ($request->input('source_type') === BannerSourceType::TEMPLATE->value) {
            $template = $this->library->findUsableForAdmin((string) $request->input('banner_template_uuid'));
            $banner = $this->activation->createAdminBannerFromTemplate($template, [
                ...$request->bannerData(),
                ...$request->ownerData(),
                'owner_type' => $request->input('owner_type'),
            ], $request->user());

            return redirect()->route('admin.banners.edit', $banner)->with('success', 'Banner created from template successfully.');
        }

        $desktopPath = $this->images->store($request->file('desktop_image'), uniqid('', true), 'desktop');
        $mobilePath = $request->hasFile('mobile_image') ? $this->images->store($request->file('mobile_image'), uniqid('', true), 'mobile') : null;

        try {
            $banner = DB::transaction(fn (): Banner => Banner::query()->create([
                ...Arr::except($request->bannerData(), ['banner_template_uuid']),
                ...$request->ownerData(),
                'source_type' => BannerSourceType::CUSTOM_UPLOAD->value,
                'banner_template_id' => null,
                'desktop_image_path' => $desktopPath,
                'mobile_image_path' => $mobilePath,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]));
        } catch (\Throwable $throwable) {
            $this->images->delete($desktopPath);
            $this->images->delete($mobilePath);

            throw $throwable;
        }

        return redirect()->route('admin.banners.edit', $banner)->with('success', 'Banner created successfully.');
    }

    public function show(Banner $banner): View
    {
        return view('admin.banners.show', ['banner' => $banner->load(['merchant', 'shop'])]);
    }

    public function edit(Banner $banner): View
    {
        abort_if($banner->trashed(), 404);

        return view('admin.banners.edit', $this->formData($banner));
    }

    public function update(UpdateBannerRequest $request, Banner $banner): RedirectResponse
    {
        abort_if($banner->trashed(), 404);
        $oldDesktop = $banner->desktop_image_path;
        $oldMobile = $banner->mobile_image_path;
        $oldWasCustom = $banner->usesCustomUpload();
        $data = [
            ...Arr::except($request->bannerData(), ['banner_template_uuid']),
            ...$request->ownerData(),
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

        return redirect()->route('admin.banners.edit', $banner)->with('success', 'Banner updated successfully.');
    }

    public function replaceTemplate(Request $request, Banner $banner): RedirectResponse
    {
        abort_if($banner->trashed(), 404);

        $data = $request->validate([
            'banner_template_uuid' => ['required', 'string', 'exists:banner_templates,uuid'],
            'apply_template_defaults' => ['required', 'in:images_only,text,all'],
        ]);

        $template = $banner->merchant_id
            ? $this->library->findUsableForMerchant($data['banner_template_uuid'])
            : $this->library->findUsableForAdmin($data['banner_template_uuid']);

        $this->activation->replaceBannerTemplate($banner, $template, $data, $request->user());

        return redirect()->route('admin.banners.edit', $banner)->with('success', 'Banner template replaced successfully.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        abort_if($banner->trashed(), 404);

        $banner->forceFill(['deleted_by' => Auth::id(), 'updated_by' => Auth::id()])->save();
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Banner deleted successfully.');
    }

    public function restore(string $banner): RedirectResponse
    {
        $banner = Banner::onlyTrashed()->where('uuid', $banner)->firstOrFail();
        $banner->restore();
        $banner->forceFill(['deleted_by' => null, 'updated_by' => Auth::id()])->save();

        return redirect()->route('admin.banners.edit', $banner)->with('success', 'Banner restored successfully.');
    }

    private function formData(Banner $banner): array
    {
        return [
            'banner' => $banner,
            'positions' => BannerPosition::options(),
            'positionMeta' => collect(BannerPosition::cases())->mapWithKeys(fn (BannerPosition $position): array => [$position->value => [
                'scope' => $position->scope(),
                'label' => $position->label(),
                'description' => $position->description(),
                'max' => $position->maxBanners(),
                'dimensions' => $position->recommendedDimensions(),
            ]])->all(),
            'linkTypes' => BannerLinkType::options(),
            'merchants' => MerchantProfile::query()->where('status', 'active')->orderBy('business_name')->get(),
            'shops' => Shop::query()->where('status', 'active')->orderBy('name')->get(),
            'bannerTemplates' => $this->library->getForAdmin(),
            'linkTargets' => $this->linkTargets(),
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

    private function linkTargets(): array
    {
        return [
            'product' => Product::query()
                ->with(['shop', 'merchant'])
                ->whereNull('deleted_at')
                ->orderBy('product_name')
                ->limit(500)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->getKey(),
                    'label' => $product->product_name.' - '.($product->shop?->name ?? 'Shop'),
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
            'shop' => Shop::query()
                ->with('merchant')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get()
                ->map(fn (Shop $shop): array => [
                    'id' => $shop->getKey(),
                    'label' => $shop->name.' - '.($shop->merchant?->business_name ?? 'Merchant'),
                    'merchant_id' => $shop->merchant_id,
                    'shop_id' => $shop->getKey(),
                ])
                ->all(),
        ];
    }
}
