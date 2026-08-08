<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Storefront\NavigationService;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(private readonly NavigationService $navigation) {}

    public function home(): View
    {
        return view('storefront.pages.home', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function category(string $slug): View
    {
        $category = ProductCategory::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        return view('storefront.pages.placeholder', [
            'pageTitle' => $category->name,
            'pageDescription' => 'Category product listing is intentionally deferred.',
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function store(string $slug): View
    {
        $shop = $this->activeShopBySlug($slug);

        return view('storefront.pages.placeholder', [
            'pageTitle' => $shop->name,
            'pageDescription' => 'Merchant storefront content is intentionally deferred.',
            'storefrontShop' => $shop,
            'storefrontNavigationCategories' => $this->navigation->getMerchantCategories($shop),
        ]);
    }

    public function storeCategory(string $slug, string $categorySlug): View
    {
        $shop = $this->activeShopBySlug($slug);
        $navigationCategories = $this->navigation->getMerchantCategories($shop);
        $category = $this->findCategoryInTree($navigationCategories, $categorySlug);

        abort_if($category === null, 404);

        return view('storefront.pages.placeholder', [
            'pageTitle' => $shop->name.' - '.$category->name,
            'pageDescription' => 'Merchant category product listing is intentionally deferred.',
            'storefrontShop' => $shop,
            'storefrontNavigationCategories' => $navigationCategories,
        ]);
    }

    private function activeShopBySlug(string $slug): Shop
    {
        return Shop::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function findCategoryInTree(iterable $categories, string $slug): ?ProductCategory
    {
        foreach ($categories as $category) {
            if ($category->slug === $slug) {
                return $category;
            }

            $found = $this->findCategoryInTree($category->children, $slug);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
