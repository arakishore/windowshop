<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteProductImagesRequest;
use App\Http\Requests\Admin\BulkUpdateProductVariantsRequest;
use App\Http\Requests\Admin\StoreProductImagesRequest;
use App\Http\Requests\Admin\StoreProductQuickCreateRequest;
use App\Http\Requests\Admin\UpdateProductAttributesRequest;
use App\Http\Requests\Admin\UpdateProductBasicRequest;
use App\Http\Requests\Admin\UpdateProductDescriptionSeoRequest;
use App\Http\Requests\Admin\UpdateProductImagesRequest;
use App\Http\Requests\Admin\UpdateProductVariantsRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Product\ProductAttributeConfigurationService;
use App\Services\Product\ProductAttributeService;
use App\Services\Product\ProductDescriptionTemplateService;
use App\Services\Product\ProductDuplicationService;
use App\Services\Product\ProductImageService;
use App\Services\Product\ProductVariantGenerationService;
use App\Services\Product\ProductVariantManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly ProductDescriptionTemplateService $descriptionTemplateService,
        private readonly ProductAttributeConfigurationService $attributeConfigurationService,
        private readonly ProductAttributeService $attributeService,
        private readonly ProductVariantGenerationService $variantGenerationService,
        private readonly ProductVariantManagementService $variantManagementService,
        private readonly ProductImageService $productImageService,
        private readonly ProductDuplicationService $productDuplicationService,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status'),
        ];

        $products = Product::query()
            ->with(['shop', 'category', 'brand', 'primaryImage'])
            ->where('shop_id', $shop->getKey())
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('product_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($filters['status'], array_keys($this->statuses()), true), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('merchant.products.index', [
            'products' => $products,
            'filters' => $filters,
            'activeShop' => $shop,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $shop = $this->activeShop($request);

        return view('merchant.products.create', [
            'product' => null,
            ...$this->sharedData($shop),
        ]);
    }

    public function store(StoreProductQuickCreateRequest $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->validated();

        abort_unless((int) $data['shop_id'] === (int) $shop->getKey(), 404);

        $product = DB::transaction(function () use ($data, $shop): Product {
            $product = Product::create([
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
                'root_product_category_id' => $shop->root_product_category_id,
                'product_category_id' => $data['product_category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'product_name' => $data['product_name'],
                'slug' => 'pending-'.Str::uuid()->toString(),
                'status' => $data['status'],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $product->updateQuietly(['slug' => $product->slugFromName()]);
            $this->variantManagementService->ensureBaseVariant($product, Auth::user());

            return $product;
        });

        $generated = $this->descriptionTemplateService->generateForProduct($product);
        $this->descriptionTemplateService->applyToProduct($product);

        return redirect()
            ->route('merchant.products.edit', $product)
            ->with($generated['found'] ? 'success' : 'info', $generated['found']
                ? 'Product created with description generated from template. Complete the remaining product details from the tabs below.'
                : 'Product created. No active description template is available for this product category.');
    }

    public function edit(Request $request, Product $product): View
    {
        $shop = $this->authorizeProduct($request, $product);
        $variantFilters = [
            'search' => $request->query('variant_search', ''),
            'status' => $request->query('variant_status', ''),
            'attributes' => $request->query('variant_attributes', []),
        ];

        return view('merchant.products.edit', [
            'product' => $product->load([
                'shop.merchant',
                'category.parent',
                'brand',
                'attributes',
                'variants.attributes.group',
                'variants.attributes.value',
                'images.attributeValues',
                'primaryImage',
            ]),
            'selectedDescriptionTemplate' => $this->descriptionTemplateService->findTemplateForProduct($product),
            'attributeMappings' => $product->category ? $this->attributeConfigurationService->forCategory($product->category) : collect(),
            'selectedAttributeValues' => $this->attributeService->selectedValues($product),
            'variantPreview' => $this->variantGenerationService->preview($product),
            'variantRows' => $this->variantManagementService->variantsForDisplay($product, $variantFilters),
            'variantFilters' => $variantFilters,
            'variantFilterOptions' => $this->variantManagementService->filterOptions($product),
            'imageAttributeMapping' => $this->productImageService->imageAttributeMapping($product),
            'imageAttributeValues' => $this->productImageService->selectableImageAttributeValues($product),
            'imageLimits' => $this->productImageService->imageLimits($product),
            ...$this->sharedData($shop, $product),
        ]);
    }

    public function update(UpdateProductBasicRequest $request, Product $product): RedirectResponse
    {
        $shop = $this->authorizeProduct($request, $product);
        $data = $request->validated();

        abort_unless((int) $data['shop_id'] === (int) $shop->getKey(), 404);

        if ($data['status'] === 'active') {
            $this->variantManagementService->assertProductCanBePublished($product);
        }

        $product->forceFill([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $data['product_category_id'],
            'brand_id' => $data['brand_id'] ?? null,
            'product_name' => $data['product_name'],
            'slug' => $this->slugForProduct($product, $data['product_name']),
            'short_description' => $this->nullable($data['short_description'] ?? null),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.products.edit', $product)
            ->with('success', 'Product basic information updated successfully.');
    }

    public function updateAttributes(UpdateProductAttributesRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->attributeService->sync($product, $request->selectedAttributes());

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'attributes'])
            ->with('success', 'Product attributes updated successfully.');
    }

    public function duplicate(Request $request, Product $product): RedirectResponse
    {
        $shop = $this->authorizeProduct($request, $product);
        $duplicate = $this->productDuplicationService->duplicate($product, Auth::user(), $shop);

        return redirect()
            ->route('merchant.products.edit', $duplicate)
            ->with('success', 'Product duplicated successfully. Review the details before activating it.');
    }

    public function archive(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $product->forceFill([
            'status' => 'archived',
            'deleted_by' => null,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.products.index')
            ->with('success', 'Product archived successfully.');
    }

    public function restoreArchive(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        abort_unless($product->status === 'archived', 404);

        $product->forceFill([
            'status' => 'draft',
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.products.index', ['status' => 'archived'])
            ->with('success', 'Product restored from archive as draft.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->validate([
            'action' => ['required', Rule::in(['mark_active', 'mark_inactive', 'archive', 'restore_archive', 'delete'])],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $count = match ($data['action']) {
            'mark_active' => $this->bulkStatus($shop, $data['product_ids'], 'active'),
            'mark_inactive' => $this->bulkStatus($shop, $data['product_ids'], 'inactive'),
            'archive' => $this->bulkArchive($shop, $data['product_ids']),
            'restore_archive' => $this->bulkRestoreArchive($shop, $data['product_ids']),
            'delete' => $this->bulkSoftDelete($shop, $data['product_ids']),
        };

        return back()->with('success', "{$count} product(s) updated successfully.");
    }

    public function generateVariants(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $result = $this->variantGenerationService->generate($product, Auth::user());

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'variants'])
            ->with('success', "{$result['created_count']} product variants generated successfully.");
    }

    public function updateVariants(UpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $updated = $this->variantManagementService->updateVariants($product, $request->variants(), Auth::user(), $request->defaultVariantId());

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'variants'])
            ->with('success', "{$updated} variant rows updated successfully.");
    }

    public function bulkUpdateVariants(BulkUpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $updated = $this->variantManagementService->bulkUpdate($product, $request->variantIds(), $request->changes(), Auth::user(), $request->appliesToAll());

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'variants'])
            ->with('success', "{$updated} variant rows updated successfully.");
    }

    public function storeImages(StoreProductImagesRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($request->hasGroupedImages()) {
            $this->productImageService->uploadGroups($product, $request->imageGroups(), Auth::id());
        } else {
            $this->productImageService->upload($product, $request->file('images', []), $request->attributeValueId(), Auth::id());
        }

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', 'Product images uploaded successfully.');
    }

    public function updateImages(UpdateProductImagesRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->productImageService->update($product, $request->imageRows(), $request->primaryImageId(), Auth::id());

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', 'Product images updated successfully.');
    }

    public function destroyImage(Request $request, Product $product, ProductImage $productImage): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->productImageService->delete($product, $productImage, Auth::id());

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', 'Product image deleted successfully.');
    }

    public function bulkDestroyImages(BulkDeleteProductImagesRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $deleted = $this->productImageService->deleteMany($product, $request->imageIds(), Auth::id());

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', "{$deleted} product images deleted successfully.");
    }

    public function updateDescriptionSeo(UpdateProductDescriptionSeoRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $data = $request->validated();
        $tab = $request->input('current_tab') === 'seo' ? 'seo' : 'description';

        $product->forceFill([
            'short_description' => $this->nullable($data['short_description'] ?? null),
            'description' => $this->nullable($data['description'] ?? null),
            'meta_title' => $this->nullable($data['meta_title'] ?? null),
            'meta_description' => $this->nullable($data['meta_description'] ?? null),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => $tab])
            ->with('success', $tab === 'seo' ? 'Product SEO updated successfully.' : 'Product description updated successfully.');
    }

    public function generateDescriptionSeo(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $generated = $this->descriptionTemplateService->generateForProduct($product);

        if (! $generated['found']) {
            return redirect()
                ->route('merchant.products.edit', ['product' => $product, 'tab' => 'description'])
                ->with('info', $generated['message']);
        }

        $this->descriptionTemplateService->applyToProduct($product, true);

        return redirect()
            ->route('merchant.products.edit', ['product' => $product, 'tab' => 'description'])
            ->with('success', 'Product description and SEO regenerated from template.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        DB::transaction(function () use ($product): void {
            $product->forceFill([
                'deleted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ])->save();

            $product->delete();
        });

        return redirect()
            ->route('merchant.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkArchive(Shop $shop, array $productIds): int
    {
        return Product::query()
            ->where('shop_id', $shop->getKey())
            ->where('merchant_id', $shop->merchant_id)
            ->whereIn('id', $productIds)
            ->where('status', '!=', 'archived')
            ->update([
                'status' => 'archived',
                'deleted_by' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkStatus(Shop $shop, array $productIds, string $status): int
    {
        return DB::transaction(function () use ($shop, $productIds, $status): int {
            $products = Product::query()
                ->where('shop_id', $shop->getKey())
                ->where('merchant_id', $shop->merchant_id)
                ->whereIn('id', $productIds)
                ->where('status', '!=', 'archived')
                ->get();

            if ($status === 'active') {
                foreach ($products as $product) {
                    $this->variantManagementService->assertProductCanBePublished($product);
                }
            }

            foreach ($products as $product) {
                $product->forceFill([
                    'status' => $status,
                    'updated_by' => Auth::id(),
                ])->save();
            }

            return $products->count();
        });
    }

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkRestoreArchive(Shop $shop, array $productIds): int
    {
        return Product::query()
            ->where('shop_id', $shop->getKey())
            ->where('merchant_id', $shop->merchant_id)
            ->whereIn('id', $productIds)
            ->where('status', 'archived')
            ->update([
                'status' => 'draft',
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkSoftDelete(Shop $shop, array $productIds): int
    {
        return DB::transaction(function () use ($shop, $productIds): int {
            $products = Product::query()
                ->where('shop_id', $shop->getKey())
                ->where('merchant_id', $shop->merchant_id)
                ->whereIn('id', $productIds)
                ->get();

            foreach ($products as $product) {
                $product->forceFill([
                    'deleted_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ])->save();
                $product->delete();
            }

            return $products->count();
        });
    }

    private function activeShop(Request $request): Shop
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $shop = $this->shopContextService->resolveActiveShop(
            $this->shopContextService->activeShops($merchant),
            $request->session()->get('active_shop_id'),
        );

        abort_unless($shop instanceof Shop, 403);

        return $shop;
    }

    private function authorizeProduct(Request $request, Product $product): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $product->shop_id === (int) $shop->getKey(), 404);
        abort_unless((int) $product->merchant_id === (int) $shop->merchant_id, 404);

        return $shop;
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedData(Shop $shop, ?Product $product = null): array
    {
        return [
            'shops' => collect([$shop->loadMissing('merchant', 'rootProductCategory')]),
            'productCategories' => $this->productCategories($shop, $product),
            'brands' => $this->brands($shop, $product),
            'statuses' => $this->statuses(),
            'productRoutePrefix' => 'merchant',
        ];
    }

    private function productCategories(Shop $shop, ?Product $product = null): Collection
    {
        $categories = ProductCategory::query()
            ->with(['parent.parent', 'children'])
            ->where(function ($query) use ($product): void {
                $query->where('status', 'active');

                if ($product?->product_category_id) {
                    $query->orWhere('id', $product->product_category_id);
                }
            })
            ->where(function ($query) use ($shop, $product): void {
                $query->where('id', $shop->root_product_category_id)
                    ->orWhereHas('parent', fn ($query) => $query->where('id', $shop->root_product_category_id)->orWhereHas('parent', fn ($query) => $query->where('id', $shop->root_product_category_id)));

                if ($product?->product_category_id) {
                    $query->orWhere('id', $product->product_category_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $paths = $this->buildCategoryPaths($categories);

        return $categories
            ->map(function (ProductCategory $category) use ($paths): ProductCategory {
                return $category
                    ->setAttribute('full_path_label', $paths[$category->getKey()] ?? $category->name)
                    ->setAttribute('root_category_id', $category->rootCategoryId())
                    ->setAttribute('is_selectable_leaf', ! $category->isRoot() && $category->isLeaf());
            })
            ->sortBy('full_path_label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function brands(Shop $shop, ?Product $product = null): Collection
    {
        return Brand::query()
            ->with('rootProductCategories:id,name')
            ->where(function ($query) use ($product): void {
                $query->where('status', 'active');

                if ($product?->brand_id) {
                    $query->orWhere('id', $product->brand_id);
                }
            })
            ->where(function ($query) use ($shop, $product): void {
                $query->whereHas('rootProductCategories', fn ($query) => $query->whereKey($shop->root_product_category_id));

                if ($product?->brand_id) {
                    $query->orWhere('id', $product->brand_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function statuses(): array
    {
        return [
            'draft' => ['label' => 'Draft', 'badge_class' => 'bg-light text-body border'],
            'active' => ['label' => 'Active', 'badge_class' => 'bg-success'],
            'inactive' => ['label' => 'Inactive', 'badge_class' => 'bg-warning'],
            'archived' => ['label' => 'Archived', 'badge_class' => 'bg-secondary'],
        ];
    }

    private function slugForProduct(Product $product, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = "{$base}-{$product->getKey()}";

        if (! Product::query()->where('slug', $slug)->whereKeyNot($product->getKey())->exists()) {
            return $slug;
        }

        return "{$base}-{$product->uuid}";
    }

    private function buildCategoryPaths(Collection $categories): array
    {
        $byId = $categories->keyBy('id');

        return $categories
            ->mapWithKeys(fn (ProductCategory $category) => [
                $category->getKey() => $this->categoryPathFromCollection($category, $byId),
            ])
            ->all();
    }

    private function categoryPathFromCollection(ProductCategory $category, Collection $byId): string
    {
        $names = [];
        $visited = [];
        $current = $category;

        while ($current && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();
            array_unshift($names, $current->name);
            $current = $current->parent_id ? $byId->get($current->parent_id) : null;
        }

        return implode(' > ', $names);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
