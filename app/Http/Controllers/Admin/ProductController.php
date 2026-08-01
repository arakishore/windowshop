<?php

namespace App\Http\Controllers\Admin;

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
use App\Models\ProductDescriptionTemplate;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\TaxClass;
use App\Services\Product\ProductAttributeConfigurationService;
use App\Services\Product\ProductAttributeService;
use App\Services\Product\ProductDescriptionTemplateService;
use App\Services\Product\ProductDuplicationService;
use App\Services\Product\ProductImageService;
use App\Services\Product\ProductPurgeService;
use App\Services\Product\ProductVariantGenerationService;
use App\Services\Product\ProductVariantManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductDescriptionTemplateService $descriptionTemplateService,
        private readonly ProductAttributeConfigurationService $attributeConfigurationService,
        private readonly ProductAttributeService $attributeService,
        private readonly ProductVariantGenerationService $variantGenerationService,
        private readonly ProductVariantManagementService $variantManagementService,
        private readonly ProductImageService $productImageService,
        private readonly ProductDuplicationService $productDuplicationService,
        private readonly ProductPurgeService $productPurgeService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'shop_id' => $request->query('shop_id'),
            'status' => $request->query('status'),
            'featured' => $request->query('featured'),
        ];

        $isTrash = $filters['status'] === 'trash';

        $products = ($isTrash ? Product::onlyTrashed() : Product::query())
            ->with(['shop.merchant', 'category', 'brand', 'primaryImage', 'deletedBy'])
            ->with(['taxClass', 'category.defaultTaxClass'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('product_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(is_numeric($filters['shop_id']), fn ($query) => $query->where('shop_id', (int) $filters['shop_id']))
            ->when(! $isTrash && in_array($filters['status'], array_keys($this->statuses()), true), fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['featured'], fn ($query, $featured) => $this->applyFeaturedFilter($query, (string) $featured))
            ->orderByDesc('created_at')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => $filters,
            ...$this->sharedData(),
            'shops' => $this->productFilterShops(),
            'statuses' => $this->statuses(includeTrash: true),
            'isTrash' => $isTrash,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => null,
            ...$this->sharedData(),
        ]);
    }

    public function store(StoreProductQuickCreateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $merchandising = $request->merchandisingConfiguration();
        $actorId = Auth::id();

        $product = DB::transaction(function () use ($data, $merchandising, $actorId): Product {
            $shop = Shop::query()->findOrFail((int) $data['shop_id']);

            $product = Product::create([
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
                'root_product_category_id' => $shop->root_product_category_id,
                'product_category_id' => $data['product_category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'tax_mode' => 'inherit',
                'tax_class_id' => null,
                'product_name' => $data['product_name'],
                'slug' => 'pending-'.Str::uuid()->toString(),
                ...$merchandising,
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $product->updateQuietly([
                'slug' => $product->slugFromName(),
            ]);

            $this->variantManagementService->ensureBaseVariant($product, Auth::user());

            return $product;
        });

        $generated = $this->descriptionTemplateService->generateForProduct($product);
        $this->descriptionTemplateService->applyToProduct($product);
        $message = $generated['found']
            ? 'Product created with description generated from template. Complete the remaining product details from the tabs below.'
            : 'Product created. No active description template is available for this product category.';

        return redirect()
            ->route('admin.products.edit', $product)
            ->with($generated['found'] ? 'success' : 'info', $message);
    }

    public function edit(Product $product): View
    {
        $variantFilters = [
            'search' => request()->query('variant_search', ''),
            'status' => request()->query('variant_status', ''),
            'attributes' => request()->query('variant_attributes', []),
        ];

        return view('admin.products.edit', [
            'product' => $product->load([
                'shop.merchant',
                'merchant.taxSetting',
                'category.parent',
                'category.defaultTaxClass.rates.components',
                'brand',
                'taxClass.rates.components',
                'attributes',
                'variants.attributes.group',
                'variants.attributes.value',
                'images.attributeValues',
                'primaryImage',
            ]),
            'selectedDescriptionTemplate' => $this->descriptionTemplateService->findTemplateForProduct($product),
            'attributeMappings' => $product->category
                ? $this->attributeConfigurationService->forCategory($product->category)
                : collect(),
            'selectedAttributeValues' => $this->attributeService->selectedValues($product),
            'variantPreview' => $this->variantGenerationService->preview($product),
            'variantRows' => $this->variantManagementService->variantsForDisplay($product, $variantFilters),
            'variantFilters' => $variantFilters,
            'variantFilterOptions' => $this->variantManagementService->filterOptions($product),
            'imageAttributeMapping' => $this->productImageService->imageAttributeMapping($product),
            'imageAttributeValues' => $this->productImageService->selectableImageAttributeValues($product),
            'imageLimits' => $this->productImageService->imageLimits($product),
            ...$this->sharedData($product),
        ]);
    }

    public function update(UpdateProductBasicRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $shop = Shop::query()->findOrFail((int) $data['shop_id']);

        if ($data['status'] === 'active') {
            $this->variantManagementService->assertProductCanBePublished($product);
        }

        $product->forceFill([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $data['product_category_id'],
            'brand_id' => $data['brand_id'] ?? null,
            ...$request->taxConfiguration(),
            'product_name' => $data['product_name'],
            'slug' => $this->slugForProduct($product, $data['product_name']),
            'short_description' => $this->nullable($data['short_description'] ?? null),
            ...$request->merchandisingConfiguration(),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Product basic information updated successfully.');
    }

    public function updateAttributes(UpdateProductAttributesRequest $request, Product $product): RedirectResponse
    {
        $this->attributeService->sync($product, $request->selectedAttributes());

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'attributes'])
            ->with('success', 'Product attributes updated successfully.');
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $duplicate = $this->productDuplicationService->duplicate($product, Auth::user());

        return redirect()
            ->route('admin.products.edit', $duplicate)
            ->with('success', 'Product duplicated successfully. Review the details before activating it.');
    }

    public function archive(Product $product): RedirectResponse
    {
        $product->forceFill([
            'status' => 'archived',
            'deleted_by' => null,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product archived successfully.');
    }

    public function restoreArchive(Product $product): RedirectResponse
    {
        abort_unless($product->status === 'archived', 404);

        $product->forceFill([
            'status' => 'draft',
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.products.index', ['status' => 'archived'])
            ->with('success', 'Product restored from archive as draft.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['mark_active', 'mark_inactive', 'mark_featured', 'remove_featured', 'archive', 'restore_archive', 'delete', 'restore_trash', 'force_delete'])],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $count = match ($data['action']) {
            'mark_active' => $this->bulkStatus($data['product_ids'], 'active'),
            'mark_inactive' => $this->bulkStatus($data['product_ids'], 'inactive'),
            'mark_featured' => $this->bulkFeatured($data['product_ids'], true),
            'remove_featured' => $this->bulkFeatured($data['product_ids'], false),
            'archive' => $this->bulkArchive($data['product_ids']),
            'restore_archive' => $this->bulkRestoreArchive($data['product_ids']),
            'delete' => $this->bulkSoftDelete($data['product_ids']),
            'restore_trash' => $this->bulkRestoreTrash($data['product_ids']),
            'force_delete' => $this->bulkForceDelete($data['product_ids']),
        };

        return back()->with('success', "{$count} product(s) updated successfully.");
    }

    public function generateVariants(Product $product): RedirectResponse
    {
        $result = $this->variantGenerationService->generate($product, Auth::user());

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'variants'])
            ->with('success', "{$result['created_count']} product variants generated successfully.");
    }

    public function updateVariants(UpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        $updated = $this->variantManagementService->updateVariants(
            $product,
            $request->variants(),
            Auth::user(),
            $request->defaultVariantId(),
        );

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'variants'])
            ->with('success', "{$updated} variant rows updated successfully.");
    }

    public function bulkUpdateVariants(BulkUpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        $updated = $this->variantManagementService->bulkUpdate(
            $product,
            $request->variantIds(),
            $request->changes(),
            Auth::user(),
            $request->appliesToAll(),
        );

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'variants'])
            ->with('success', "{$updated} variant rows updated successfully.");
    }

    public function updateDescriptionSeo(UpdateProductDescriptionSeoRequest $request, Product $product): RedirectResponse
    {
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
            ->route('admin.products.edit', ['product' => $product, 'tab' => $tab])
            ->with('success', $tab === 'seo' ? 'Product SEO updated successfully.' : 'Product description updated successfully.');
    }

    public function storeImages(StoreProductImagesRequest $request, Product $product): RedirectResponse
    {
        if ($request->hasGroupedImages()) {
            $this->productImageService->uploadGroups($product, $request->imageGroups(), Auth::id());
        } else {
            $this->productImageService->upload(
                $product,
                $request->file('images', []),
                $request->attributeValueId(),
                Auth::id(),
            );
        }

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', 'Product images uploaded successfully.');
    }

    public function updateImages(UpdateProductImagesRequest $request, Product $product): RedirectResponse
    {
        $this->productImageService->update(
            $product,
            $request->imageRows(),
            $request->primaryImageId(),
            Auth::id(),
        );

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', 'Product images updated successfully.');
    }

    public function destroyImage(Product $product, ProductImage $productImage): RedirectResponse
    {
        $this->productImageService->delete($product, $productImage, Auth::id());

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', 'Product image deleted successfully.');
    }

    public function bulkDestroyImages(BulkDeleteProductImagesRequest $request, Product $product): RedirectResponse
    {
        $deleted = $this->productImageService->deleteMany($product, $request->imageIds(), Auth::id());

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'images'])
            ->with('success', "{$deleted} product images deleted successfully.");
    }

    public function generateDescriptionSeo(Product $product): RedirectResponse
    {
        $generated = $this->descriptionTemplateService->generateForProduct($product);

        if (! $generated['found']) {
            return redirect()
                ->route('admin.products.edit', ['product' => $product, 'tab' => 'description'])
                ->with('info', $generated['message']);
        }

        $this->descriptionTemplateService->applyToProduct($product, true);

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'tab' => 'description'])
            ->with('success', 'Product description and SEO regenerated from template.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product): void {
            $product->forceFill([
                'deleted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ])->save();

            $product->delete();
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function restoreTrash(Product $product): RedirectResponse
    {
        abort_unless($product->trashed(), 404);

        DB::transaction(function () use ($product): void {
            $product->restore();
            $product->forceFill([
                'status' => 'draft',
                'deleted_by' => null,
                'updated_by' => Auth::id(),
            ])->save();
        });

        return redirect()
            ->route('admin.products.index', ['status' => 'trash'])
            ->with('success', 'Product restored as draft successfully.');
    }

    public function forceDestroy(Product $product): RedirectResponse
    {
        abort_unless($product->trashed(), 404);

        $this->productPurgeService->purge($product);

        return redirect()
            ->route('admin.products.index', ['status' => 'trash'])
            ->with('success', 'Product permanently deleted successfully.');
    }

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkArchive(array $productIds): int
    {
        return Product::query()
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
    private function bulkFeatured(array $productIds, bool $isFeatured): int
    {
        return Product::query()
            ->whereIn('id', $productIds)
            ->update([
                'is_featured' => $isFeatured,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkStatus(array $productIds, string $status): int
    {
        return DB::transaction(function () use ($productIds, $status): int {
            $products = Product::query()
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
    private function bulkRestoreArchive(array $productIds): int
    {
        return Product::query()
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
    private function bulkSoftDelete(array $productIds): int
    {
        return DB::transaction(function () use ($productIds): int {
            $products = Product::query()
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

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkRestoreTrash(array $productIds): int
    {
        return DB::transaction(function () use ($productIds): int {
            $products = Product::onlyTrashed()
                ->whereIn('id', $productIds)
                ->get();

            foreach ($products as $product) {
                $product->restore();
                $product->forceFill([
                    'status' => 'draft',
                    'deleted_by' => null,
                    'updated_by' => Auth::id(),
                ])->save();
            }

            return $products->count();
        });
    }

    /**
     * @param array<int, int|string> $productIds
     */
    private function bulkForceDelete(array $productIds): int
    {
        $products = Product::onlyTrashed()
            ->whereIn('id', $productIds)
            ->get();

        foreach ($products as $product) {
            $this->productPurgeService->purge($product);
        }

        return $products->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedData(?Product $product = null): array
    {
        return [
            'shops' => $this->shops($product),
            'productCategories' => $this->productCategories($product),
            'brands' => $this->brands($product),
            'taxClasses' => $this->taxClasses(),
            'merchantTaxEnabled' => $this->merchantTaxEnabled($product),
            'statuses' => $this->statuses(),
        ];
    }

    private function shops(?Product $product = null): Collection
    {
        return Shop::query()
            ->with(['merchant', 'rootProductCategory'])
            ->where(function ($query) use ($product): void {
                $query->whereIn('status', ['pending', 'active']);

                if ($product?->shop_id) {
                    $query->orWhere('id', $product->shop_id);
                }
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    private function productFilterShops(): Collection
    {
        return Shop::query()
            ->with('merchant')
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'active'])
                    ->orWhereHas('products');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    private function productCategories(?Product $product = null): Collection
    {
        $categories = ProductCategory::query()
            ->with(['parent.parent', 'children'])
            ->with(['defaultTaxClass.rates.components'])
            ->where(function ($query) use ($product): void {
                $query->where('status', 'active');

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

    private function brands(?Product $product = null): Collection
    {
        $shopRootCategoryIds = $this->shops($product)
            ->pluck('root_product_category_id')
            ->filter()
            ->unique()
            ->values();

        return Brand::query()
            ->with('rootProductCategories:id,name')
            ->where(function ($query) use ($product): void {
                $query->where('status', 'active');

                if ($product?->brand_id) {
                    $query->orWhere('id', $product->brand_id);
                }
            })
            ->where(function ($query) use ($product, $shopRootCategoryIds): void {
                $query->whereHas('rootProductCategories', fn ($query) => $query->whereIn('product_categories.id', $shopRootCategoryIds));

                if ($product?->brand_id) {
                    $query->orWhere('id', $product->brand_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function taxClasses(): Collection
    {
        return TaxClass::query()
            ->active()
            ->with(['rates' => fn ($query) => $query
                ->active()
                ->with('components')
                ->orderByDesc('effective_from')
                ->orderBy('priority')
                ->orderByDesc('id')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function merchantTaxEnabled(?Product $product = null): bool
    {
        if (! $product) {
            return false;
        }

        $product->loadMissing('merchant.taxSetting');

        return (bool) $product->merchant?->taxSetting?->tax_enabled;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function statuses(bool $includeTrash = false): array
    {
        $statuses = [
            'draft' => ['label' => 'Draft', 'badge_class' => 'bg-light text-body border'],
            'active' => ['label' => 'Active', 'badge_class' => 'bg-success'],
            'inactive' => ['label' => 'Inactive', 'badge_class' => 'bg-warning'],
            'archived' => ['label' => 'Archived', 'badge_class' => 'bg-secondary'],
        ];

        if ($includeTrash) {
            $statuses['trash'] = ['label' => 'Trash', 'badge_class' => 'bg-danger'];
        }

        return $statuses;
    }

    private function applyFeaturedFilter($query, string $featured): void
    {
        match ($featured) {
            'current' => $query->currentlyFeatured(),
            'scheduled' => $query->scheduledFeatured(),
            'expired' => $query->expiredFeatured(),
            'not_featured' => $query->where('is_featured', false),
            default => null,
        };
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
