<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\CatalogueMasterRequest;
use App\Models\MerchantProfile;
use App\Models\ProductAttributeGroup;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogueMasterController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $categories = $this->categoriesForShop($shop);
        $categoryPaths = $this->buildCategoryPaths($categories);

        return view('merchant.catalogue-masters.index', [
            'activeShop' => $shop->loadMissing('rootProductCategory'),
            'categories' => $categories,
            'categoryPaths' => $categoryPaths,
            'attributeGroups' => $this->attributeGroupsForShop($shop),
            'requests' => $this->requestsForShop($shop),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->validate([
            'request_type' => ['required', Rule::in([CatalogueMasterRequest::TYPE_CATEGORY, CatalogueMasterRequest::TYPE_ATTRIBUTE])],
            'suggested_name' => ['required', 'string', 'max:255'],
            'parent_product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'example_product_name' => ['nullable', 'string', 'max:255'],
        ]);

        $parentId = $data['parent_product_category_id'] ?? null;

        if ($parentId) {
            $parent = ProductCategory::query()->with('parent.parent')->find($parentId);

            if (! $parent || $parent->rootCategoryId() !== (int) $shop->root_product_category_id) {
                throw ValidationException::withMessages([
                    'parent_product_category_id' => 'Choose a parent category from the active shop type.',
                ]);
            }
        }

        CatalogueMasterRequest::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'request_type' => $data['request_type'],
            'suggested_name' => $data['suggested_name'],
            'parent_product_category_id' => $parentId,
            'description' => $data['description'] ?? null,
            'example_product_name' => $data['example_product_name'] ?? null,
            'status' => CatalogueMasterRequest::STATUS_PENDING,
            'requested_by' => Auth::id(),
        ]);

        return redirect()
            ->route('merchant.catalogue-masters.index')
            ->with('success', 'Catalogue master request submitted for admin review.');
    }

    private function activeShop(Request $request): Shop
    {
        $merchant = MerchantProfile::query()
            ->where('user_id', $request->user()?->getKey())
            ->first();

        abort_unless($merchant instanceof MerchantProfile, 403);

        $shop = $this->shopContextService->resolveActiveShop(
            $this->shopContextService->activeShops($merchant),
            $request->session()->get('active_shop_id'),
        );

        abort_unless($shop instanceof Shop, 403);

        return $shop;
    }

    private function categoriesForShop(Shop $shop): Collection
    {
        $categories = ProductCategory::query()
            ->with(['parent.parent', 'children'])
            ->where('status', 'active')
            ->where(function ($query) use ($shop): void {
                $query->whereKey($shop->root_product_category_id)
                    ->orWhereHas('parent', fn ($query) => $query
                        ->whereKey($shop->root_product_category_id)
                        ->orWhereHas('parent', fn ($query) => $query->whereKey($shop->root_product_category_id)));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (ProductCategory $category): ProductCategory {
                return $category
                    ->setAttribute('root_category_id', $category->rootCategoryId())
                    ->setAttribute('is_selectable_leaf', ! $category->isRoot() && $category->isLeaf());
            });

        return $this->flattenCategoryTree($categories);
    }

    private function attributeGroupsForShop(Shop $shop): Collection
    {
        return ProductAttributeGroup::query()
            ->with([
                'values' => fn ($query) => $query->where('status', 'active'),
                'categoryMappings' => fn ($query) => $query->where('root_product_category_id', $shop->root_product_category_id),
            ])
            ->where('status', 'active')
            ->whereHas('categoryMappings', fn ($query) => $query->where('root_product_category_id', $shop->root_product_category_id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (ProductAttributeGroup $group): int => (int) ($group->categoryMappings->first()?->sort_order ?? $group->sort_order))
            ->values();
    }

    private function requestsForShop(Shop $shop): Collection
    {
        return CatalogueMasterRequest::query()
            ->with(['parentCategory', 'reviewedBy'])
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->latest()
            ->limit(20)
            ->get();
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

    private function flattenCategoryTree(Collection $categories, ?int $parentId = null): Collection
    {
        $result = collect();
        $children = $categories
            ->filter(fn (ProductCategory $category): bool => $category->parent_id === $parentId)
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ]);

        foreach ($children as $category) {
            $result->push($category);
            $result = $result->concat($this->flattenCategoryTree($categories, $category->getKey()));
        }

        return $result->values();
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
}
