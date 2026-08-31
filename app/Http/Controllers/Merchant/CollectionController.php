<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreCollectionRequest;
use App\Http\Requests\Merchant\UpdateCollectionRequest;
use App\Models\Collection as ProductCollection;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status', ''),
        ];

        $collections = ProductCollection::query()
            ->withCount('products')
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(in_array($filters['status'], [ProductCollection::STATUS_ACTIVE, ProductCollection::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('merchant.collections.index', [
            'collections' => $collections,
            'filters' => $filters,
            'activeShop' => $shop,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('merchant.collections.create', [
            'collection' => new ProductCollection(['status' => ProductCollection::STATUS_ACTIVE, 'sort_order' => 0]),
            'activeShop' => $this->activeShop($request),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreCollectionRequest $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->collectionData();

        $collection = ProductCollection::query()->create([
            ...$data,
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'slug' => $this->uniqueSlug($shop, $data['name']),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('merchant.collections.edit', $collection)
            ->with('success', 'Collection created successfully.');
    }

    public function edit(Request $request, ProductCollection $collection): View
    {
        $shop = $this->authorizeCollection($request, $collection);

        return view('merchant.collections.edit', [
            'collection' => $collection,
            'activeShop' => $shop,
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateCollectionRequest $request, ProductCollection $collection): RedirectResponse
    {
        $shop = $this->authorizeCollection($request, $collection);
        $data = $request->collectionData();

        $collection->forceFill([
            ...$data,
            'slug' => $this->uniqueSlug($shop, $data['name'], $collection),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.collections.edit', $collection)
            ->with('success', 'Collection updated successfully.');
    }

    public function destroy(Request $request, ProductCollection $collection): RedirectResponse
    {
        $this->authorizeCollection($request, $collection);

        DB::transaction(function () use ($collection): void {
            $collection->forceFill([
                'deleted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ])->save();

            $collection->products()->detach();
            $collection->delete();
        });

        return redirect()
            ->route('merchant.collections.index')
            ->with('success', 'Collection deleted successfully.');
    }

    public function toggleStatus(Request $request, ProductCollection $collection): RedirectResponse
    {
        $this->authorizeCollection($request, $collection);

        $collection->forceFill([
            'status' => $collection->status === ProductCollection::STATUS_ACTIVE
                ? ProductCollection::STATUS_INACTIVE
                : ProductCollection::STATUS_ACTIVE,
            'updated_by' => Auth::id(),
        ])->save();

        return back()->with('success', 'Collection status updated successfully.');
    }

    public function products(Request $request, ProductCollection $collection): View
    {
        $shop = $this->authorizeCollection($request, $collection);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category_id' => (int) $request->query('category_id', 0),
            'status' => $request->query->has('status') ? (string) $request->query('status') : 'active',
            'selected_search' => trim((string) $request->query('selected_search', '')),
        ];
        $productStatuses = $this->productStatuses();

        if (! array_key_exists($filters['status'], $productStatuses) && $filters['status'] !== '') {
            $filters['status'] = 'active';
        }

        $categoryOptions = $this->productCategoryOptions($shop);

        if ($filters['category_id'] > 0 && ! $categoryOptions->has($filters['category_id'])) {
            $filters['category_id'] = 0;
        }

        $selectedProductIds = $collection->products()->pluck('products.id')->all();
        $selectedProductCount = count($selectedProductIds);

        $selectedProducts = $collection->products()
            ->with(['category', 'brand'])
            ->when($filters['selected_search'] !== '', function ($query) use ($filters): void {
                $search = $filters['selected_search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('product_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->get();

        $availableProducts = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->whereNull('deleted_at')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('product_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['category_id'] > 0, fn ($query) => $query->where('product_category_id', $filters['category_id']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($selectedProductIds !== [], fn ($query) => $query->whereNotIn('id', $selectedProductIds))
            ->orderBy('product_name')
            ->paginate(10)
            ->withQueryString();

        return view('merchant.collections.products', [
            'collection' => $collection,
            'selectedProducts' => $selectedProducts,
            'selectedProductCount' => $selectedProductCount,
            'availableProducts' => $availableProducts,
            'filters' => $filters,
            'categoryOptions' => $categoryOptions,
            'productStatuses' => $productStatuses,
            'activeShop' => $shop,
        ]);
    }

    public function attachProducts(Request $request, ProductCollection $collection): RedirectResponse
    {
        $shop = $this->authorizeCollection($request, $collection);
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where(fn ($query) => $query
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at'))],
        ]);

        $productIds = Product::query()
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->whereIn('id', $data['product_ids'])
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        DB::transaction(function () use ($collection, $productIds): void {
            foreach ($productIds as $productId) {
                $collection->products()->syncWithoutDetaching([
                    $productId => ['sort_order' => 0],
                ]);
            }
        });

        return back()->with('success', count($productIds).' product(s) added to the collection.');
    }

    public function detachProduct(Request $request, ProductCollection $collection, Product $product): RedirectResponse
    {
        $shop = $this->authorizeCollection($request, $collection);
        abort_unless((int) $product->merchant_id === (int) $shop->merchant_id && (int) $product->shop_id === (int) $shop->getKey(), 404);

        $collection->products()->detach($product->getKey());

        return back()->with('success', 'Product removed from the collection.');
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

    private function authorizeCollection(Request $request, ProductCollection $collection): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $collection->shop_id === (int) $shop->getKey(), 404);
        abort_unless((int) $collection->merchant_id === (int) $shop->merchant_id, 404);

        return $shop;
    }

    private function uniqueSlug(Shop $shop, string $name, ?ProductCollection $ignore = null): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;
        $suffix = 2;

        while (ProductCollection::query()
            ->where('shop_id', $shop->getKey())
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, array{label: string, badge_class: string}>
     */
    private function statuses(): array
    {
        return [
            ProductCollection::STATUS_ACTIVE => ['label' => 'Active', 'badge_class' => 'bg-success'],
            ProductCollection::STATUS_INACTIVE => ['label' => 'Inactive', 'badge_class' => 'bg-light text-body border'],
        ];
    }

    /**
     * @return array<string, array{label: string, badge_class: string}>
     */
    private function productStatuses(): array
    {
        return [
            'active' => ['label' => 'Active', 'badge_class' => 'bg-success'],
            'draft' => ['label' => 'Draft', 'badge_class' => 'bg-light text-body border'],
            'inactive' => ['label' => 'Inactive', 'badge_class' => 'bg-warning'],
            'archived' => ['label' => 'Archived', 'badge_class' => 'bg-secondary'],
        ];
    }

    private function productCategoryOptions(Shop $shop): Collection
    {
        $categories = ProductCategory::query()
            ->with('parent.parent')
            ->whereHas('products', fn ($query) => $query
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $paths = $this->buildCategoryPaths($categories);

        return $categories
            ->sortBy(fn (ProductCategory $category): string => $paths[$category->getKey()] ?? $category->name)
            ->mapWithKeys(fn (ProductCategory $category): array => [
                $category->getKey() => $paths[$category->getKey()] ?? $category->name,
            ]);
    }

    private function buildCategoryPaths(Collection $categories): array
    {
        $byId = $categories->keyBy('id');

        return $categories
            ->mapWithKeys(fn (ProductCategory $category): array => [
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
            $current = $current->parent_id ? $byId->get($current->parent_id) : $current->parent;
        }

        return implode(' > ', $names);
    }
}
