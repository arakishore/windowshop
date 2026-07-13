<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShopRequest;
use App\Http\Requests\Admin\UpdateShopRequest;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ShopAudience;
use App\Services\Image\ImageVariantService;
use App\Services\Merchant\MerchantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class MerchantShopController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchantService,
        private readonly ImageVariantService $imageVariantService,
    ) {
    }

    public function index(MerchantProfile $merchant): View
    {
        $shops = Shop::query()
            ->where('merchant_id', $merchant->getKey())
            ->with(['rootProductCategory', 'audiences', 'city'])
            ->orderByDesc('created_at')
            ->paginate((int) config('admin.pagination.per_page', 15));

        return view('admin.merchants.shops.index', [
            ...$this->workspaceData($merchant),
            'shops' => $shops,
            'totalShops' => Shop::query()->where('merchant_id', $merchant->getKey())->count(),
        ]);
    }

    public function create(MerchantProfile $merchant): View
    {
        return view('admin.merchants.shops.create', [
            ...$this->workspaceData($merchant),
            ...$this->formData(),
            'shop' => null,
            'selectedAudienceIds' => collect(old('audience_ids', []))->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function store(StoreShopRequest $request, MerchantProfile $merchant): RedirectResponse
    {
        $this->saveShop(new Shop(), $merchant, $request->validated(), $request, Auth::id());

        return redirect()
            ->route('admin.merchants.shops.index', $merchant)
            ->with('success', 'Shop created successfully.');
    }

    public function show(MerchantProfile $merchant, Shop $shop): View
    {
        $this->assertOwnedByMerchant($merchant, $shop);

        return view('admin.merchants.shops.show', [
            ...$this->workspaceData($merchant),
            'shop' => $shop->load(['rootProductCategory', 'audiences', 'country', 'state', 'city']),
        ]);
    }

    public function edit(MerchantProfile $merchant, Shop $shop): View
    {
        $this->assertOwnedByMerchant($merchant, $shop);

        $shop->load('audiences');

        return view('admin.merchants.shops.edit', [
            ...$this->workspaceData($merchant),
            ...$this->formData($shop),
            'shop' => $shop,
            'selectedAudienceIds' => collect(old('audience_ids', $shop->audiences->pluck('id')->all()))
                ->map(fn ($id) => (int) $id)
                ->all(),
        ]);
    }

    public function update(UpdateShopRequest $request, MerchantProfile $merchant, Shop $shop): RedirectResponse
    {
        $this->assertOwnedByMerchant($merchant, $shop);

        $this->saveShop($shop, $merchant, $request->validated(), $request, Auth::id());

        return redirect()
            ->route('admin.merchants.shops.edit', [$merchant, $shop])
            ->with('success', 'Shop updated successfully.');
    }

    public function destroy(MerchantProfile $merchant, Shop $shop): RedirectResponse
    {
        $this->assertOwnedByMerchant($merchant, $shop);

        DB::transaction(function () use ($shop): void {
            $shop->forceFill([
                'status' => 'deleted',
                'deleted_by' => Auth::id(),
            ])->save();

            $shop->delete();
        });

        return redirect()
            ->route('admin.merchants.shops.index', $merchant)
            ->with('success', 'Shop deleted successfully.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveShop(Shop $shop, MerchantProfile $merchant, array $data, Request $request, ?int $actorId): Shop
    {
        return DB::transaction(function () use ($shop, $merchant, $data, $request, $actorId): Shop {
            $isNew = ! $shop->exists;
            $currentRootCategoryId = $shop->exists ? (int) $shop->root_product_category_id : null;
            $newRootCategoryId = (int) $data['root_product_category_id'];

            if (! $isNew && $currentRootCategoryId !== $newRootCategoryId) {
                $this->assertShopTypeCanChange($shop, $newRootCategoryId);
            }

            $logoPath = $shop->logo_path;
            $bannerPath = $shop->banner_path;

            $shop->forceFill([
                'merchant_id' => $merchant->getKey(),
                'root_product_category_id' => $newRootCategoryId,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name'], $shop),
                'short_description' => $this->nullable($data['short_description'] ?? null),
                'description' => $this->nullable($data['description'] ?? null),
                'logo_path' => $logoPath,
                'banner_path' => $bannerPath,
                'email' => $this->nullable($data['email'] ?? null),
                'mobile' => $this->nullable($data['mobile'] ?? null),
                'whatsapp_number' => $this->nullable($data['whatsapp_number'] ?? null),
                'website_url' => $this->nullable($data['website_url'] ?? null),
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $this->nullable($data['address_line_2'] ?? null),
                'landmark' => $this->nullable($data['landmark'] ?? null),
                'country_id' => $data['country_id'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'pincode' => $this->nullable($data['pincode'] ?? null),
                'latitude' => $this->nullable($data['latitude'] ?? null),
                'longitude' => $this->nullable($data['longitude'] ?? null),
                'status' => $data['status'],
                'admin_note' => $this->nullable($data['admin_note'] ?? null),
                'updated_by' => $actorId,
            ]);

            if ($isNew) {
                $shop->created_by = $actorId;
            }

            $shop->save();

            if (! $isNew && $currentRootCategoryId !== $newRootCategoryId) {
                Product::query()
                    ->where('shop_id', $shop->getKey())
                    ->update(['root_product_category_id' => $newRootCategoryId]);
            }

            if ($request->boolean('remove_logo')) {
                $this->deleteImageDirectory($logoPath);
                $logoPath = null;
            }

            if ($request->boolean('remove_banner')) {
                $this->deleteImageDirectory($bannerPath);
                $bannerPath = null;
            }

            if ($request->hasFile('logo')) {
                $this->deleteImageDirectory($logoPath);
                $logoPath = $this->storeImage($request, 'logo', 'shop_logo', "shops/{$shop->getKey()}/logo");
            }

            if ($request->hasFile('banner')) {
                $this->deleteImageDirectory($bannerPath);
                $bannerPath = $this->storeImage($request, 'banner', 'shop_banner', "shops/{$shop->getKey()}/banner");
            }

            if ($logoPath !== $shop->logo_path || $bannerPath !== $shop->banner_path) {
                $shop->forceFill([
                    'logo_path' => $logoPath,
                    'banner_path' => $bannerPath,
                ])->save();
            }

            $shop->audiences()->sync($data['audience_ids'] ?? []);

            return $shop;
        });
    }

    private function uniqueSlug(string $name, Shop $shop): string
    {
        $base = Str::slug($name) ?: Str::uuid()->toString();
        $slug = $base;
        $suffix = 2;

        while (Shop::query()
            ->where('slug', $slug)
            ->when($shop->exists, fn ($query) => $query->whereKeyNot($shop->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeImage(Request $request, string $field, string $profile, string $directory): string
    {
        try {
            $paths = $this->imageVariantService->store($request->file($field), $profile, $directory);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        return $paths['web'] ?? array_values($paths)[0];
    }

    private function deleteImageDirectory(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->deleteDirectory(dirname($path));
    }

    private function assertOwnedByMerchant(MerchantProfile $merchant, Shop $shop): void
    {
        abort_unless((int) $shop->merchant_id === (int) $merchant->getKey(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceData(MerchantProfile $merchant): array
    {
        return [
            'merchant' => $this->merchantService->loadForManage($merchant),
            'activeTab' => 'shops',
            'businessTypes' => $this->merchantService->businessTypes(),
            'accountStatuses' => $this->merchantService->accountStatuses(),
            'verificationStatuses' => $this->merchantService->verificationStatuses(),
            'accountStatusBadgeClasses' => $this->merchantService->accountStatusBadgeClasses(),
            'verificationStatusBadgeClasses' => $this->merchantService->verificationStatusBadgeClasses(),
            'shopStatuses' => config('admin.shop.statuses', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Shop $shop = null): array
    {
        $defaultLocation = $this->merchantService->defaultBusinessLocation();

        $countryId = (int) old('country_id', $shop?->country_id ?? $defaultLocation['country_id']);
        $stateId = (int) old('state_id', $shop?->state_id ?? $defaultLocation['state_id']);

        return [
            'categories' => ProductCategory::query()
                ->where(function ($query) use ($shop): void {
                    $query->whereNull('parent_id')
                        ->where('status', 'active');

                    if ($shop?->root_product_category_id) {
                        $query->orWhere('id', $shop->root_product_category_id);
                    }
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'audiences' => ShopAudience::query()
                ->where(function ($query) use ($shop): void {
                    $query->where('status', 'active');

                    if ($shop?->exists) {
                        $query->orWhereIn('id', $shop->audiences()->select('shop_audiences.id'));
                    }
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'countries' => $this->merchantService->activeCountries(),
            'states' => $countryId ? $this->merchantService->activeStates($countryId) : collect(),
            'cities' => $countryId && $stateId ? $this->merchantService->citiesForState($countryId, $stateId) : collect(),
            'defaultLocation' => $defaultLocation,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assertShopTypeCanChange(Shop $shop, int $newRootCategoryId): void
    {
        $categoryIds = Product::query()
            ->where('shop_id', $shop->getKey())
            ->pluck('product_category_id')
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return;
        }

        $categories = ProductCategory::query()
            ->with('parent.parent')
            ->whereIn('id', $categoryIds)
            ->get();

        $hasInvalidProductCategory = $categories->contains(
            fn (ProductCategory $category): bool => $category->rootCategoryId() !== $newRootCategoryId
        );

        if ($hasInvalidProductCategory) {
            throw ValidationException::withMessages([
                'root_product_category_id' => 'This shop type cannot be changed because one or more existing products belong to categories outside the selected shop type.',
            ]);
        }
    }
}
