<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PostalCode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ShopAudience;
use App\Models\WishlistItem;
use App\Services\Banner\BannerService;
use App\Services\Cart\CartPageService;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Delivery\ShopDeliveryServiceabilityService;
use App\Services\Storefront\CustomerLocationService;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\ProductLocationSorter;
use App\Services\Storefront\ProductListingService;
use App\Services\Storefront\StorefrontCustomerContext;
use App\Services\Storefront\StorefrontUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly BannerService $banners,
        private readonly ProductListingService $productListings,
        private readonly StorefrontCustomerContext $customerContext,
        private readonly StorefrontUrlService $urls,
    ) {}

    public function home(): View
    {
        return view('storefront.pages.home', [
            'heroBanners' => $this->banners->getMarketplaceHeroBanners(),
            'homepageCategories' => $this->homepageCategoryCards(),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function about(): View
    {
        return view('storefront.pages.about', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function stores(Request $request, CustomerLocationService $location, ProductLocationSorter $locationSorter): View
    {
        $selectedPostalCode = $location->postalCode($request);
        $postalCodeRecord = $selectedPostalCode ? $location->postalCodeRecord($selectedPostalCode) : null;
        $locationDistrict = trim((string) ($postalCodeRecord?->district ?? ''));
        $locationState = trim((string) ($postalCodeRecord?->state ?? ''));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'area' => trim((string) $request->query('area', '')),
            'shop_type' => trim((string) $request->query('shop_type', '')),
            'audience' => trim((string) $request->query('audience', '')),
        ];

        $baseQuery = $this->storeDiscoveryBaseQuery();
        $this->applyStoreLocationScope($baseQuery, $locationDistrict, $locationState);

        $areaOptions = (clone $baseQuery)
            ->reorder()
            ->whereNotNull('landmark')
            ->where('landmark', '!=', '')
            ->distinct()
            ->orderBy('landmark')
            ->pluck('landmark')
            ->filter()
            ->values();

        $shopTypeOptions = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereIn('id', (clone $baseQuery)->reorder()->select('root_product_category_id')->distinct())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $audienceOptions = ShopAudience::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereHas('shops', fn ($query) => $query->whereIn('shops.id', (clone $baseQuery)->reorder()->select('shops.id')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $storesQuery = clone $baseQuery;
        $this->applyStoreFilters($storesQuery, $filters);
        $locationSorter->apply($storesQuery, $selectedPostalCode, 'shops.pincode');

        $stores = $storesQuery
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Shop $shop): array => $this->storeCardData($shop));

        return view('storefront.pages.stores', [
            'stores' => $stores,
            'filters' => $filters,
            'hasActiveStoreFilters' => collect($filters)->filter(fn ($value): bool => $value !== '')->isNotEmpty(),
            'selectedPostalCode' => $selectedPostalCode,
            'locationDistrict' => $locationDistrict,
            'locationState' => $locationState,
            'storeHeroMap' => $this->storeHeroMapData($postalCodeRecord),
            'areaOptions' => $areaOptions,
            'shopTypeOptions' => $shopTypeOptions,
            'audienceOptions' => $audienceOptions,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    private function storeDiscoveryBaseQuery()
    {
        return Shop::query()
            ->with([
                'rootProductCategory:id,name',
                'audiences:id,name,slug',
                'city:id,name',
                'state:id,name',
                'country:id,name',
            ])
            ->where('status', 'active')
            ->whereHas('merchant', fn ($query) => $query->where('status', 'active'));
    }

    private function applyStoreLocationScope($query, string $district, string $state): void
    {
        if ($district === '') {
            return;
        }

        $postalCodes = PostalCode::query()
            ->active()
            ->where('district', $district)
            ->when($state !== '', fn ($query) => $query->where('state', $state))
            ->pluck('postal_code')
            ->all();

        $query->where(function ($query) use ($district, $postalCodes): void {
            $query->whereHas('city', fn ($query) => $query->where('name', $district));

            if ($postalCodes !== []) {
                $query->orWhereIn('pincode', $postalCodes);
            }
        });
    }

    private function applyStoreFilters($query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('landmark', 'like', "%{$search}%")
                    ->orWhere('address_line_1', 'like', "%{$search}%")
                    ->orWhere('address_line_2', 'like', "%{$search}%");
            });
        }

        if ($filters['area'] !== '') {
            $query->where('landmark', $filters['area']);
        }

        if ($filters['shop_type'] !== '') {
            $query->whereHas('rootProductCategory', fn ($query) => $query->where('slug', $filters['shop_type']));
        }

        if ($filters['audience'] !== '') {
            $query->whereHas('audiences', fn ($query) => $query->where('slug', $filters['audience']));
        }
    }

    private function storeCardData(Shop $shop): array
    {
        $imagePath = $shop->banner_path ?: $shop->logo_path;
        $storeUrl = route('storefront.store.show', $shop->slug);
        $fullAddress = collect([
            $shop->address_line_1,
            $shop->address_line_2,
            $shop->landmark,
            $shop->city?->name,
            $shop->pincode,
        ])->filter()->implode(', ');

        $latitude = is_numeric($shop->latitude) ? (float) $shop->latitude : null;
        $longitude = is_numeric($shop->longitude) ? (float) $shop->longitude : null;

        if ($latitude !== null && $longitude !== null) {
            $mapsUrl = "https://www.google.com/maps?q={$latitude},{$longitude}";
        } else {
            $searchQuery = collect([
                $shop->name,
                $fullAddress,
                $shop->state?->name,
                $shop->country?->name,
            ])->filter()->implode(', ');
            $mapsUrl = $searchQuery !== ''
                ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($searchQuery)
                : null;
        }

        return [
            'name' => $shop->name,
            'address' => $fullAddress ?: null,
            'maps_url' => $mapsUrl,
            'shop_type' => $shop->rootProductCategory?->name,
            'audiences' => $shop->audiences->pluck('name')->values()->all(),
            'image' => $imagePath ? 'storage/'.$imagePath : 'assets/storefront/images/no-image-icon.png',
            'logo' => $shop->logo_path ? 'storage/'.$shop->logo_path : null,
            'initials' => $this->storeInitials($shop->name),
            'website_url' => $shop->website_url ?: $storeUrl,
            'store_url' => $storeUrl,
        ];
    }

    private function storeInitials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = collect($words)
            ->filter()
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(3)
            ->implode('');

        return $initials !== '' ? $initials : 'WS';
    }

    private function storeHeroMapData(?PostalCode $postalCode): ?array
    {
        if (
            $postalCode === null
            || ! is_numeric($postalCode->latitude)
            || ! is_numeric($postalCode->longitude)
        ) {
            return null;
        }

        return [
            'latitude' => (float) $postalCode->latitude,
            'longitude' => (float) $postalCode->longitude,
            'zoom' => 11,
        ];
    }

    public function testimonials(): View
    {
        $testimonials = [
            [
                'name' => 'Priya Shah',
                'avatar' => 'assets/storefront/images/avatar/avatar-4.jpg',
                'quote' => 'WindowShop helped me find nearby stores before stepping out. I could compare options and visit the right shop directly.',
                'product_name' => 'Local Store Discovery',
                'product_image' => 'assets/storefront/images/product/product-1.jpg',
                'tag' => 'Customer Story',
            ],
            [
                'name' => 'Rahul Mehta',
                'avatar' => 'assets/storefront/images/avatar/avatar-5.jpg',
                'quote' => 'Our shop finally has a clean online presence. Customers now ask about products they already saw on our page.',
                'product_name' => 'Shop Website Page',
                'product_image' => 'assets/storefront/images/product/product-2.jpg',
                'tag' => 'Merchant Story',
            ],
            [
                'name' => 'Anjali Verma',
                'avatar' => 'assets/storefront/images/avatar/avatar-6.jpg',
                'quote' => 'It feels useful for daily buying. I can discover local sellers, check offers, and keep trusted shops in mind.',
                'product_name' => 'Daily Local Buying',
                'product_image' => 'assets/storefront/images/product/product-3.jpg',
                'tag' => 'Customer Story',
            ],
            [
                'name' => 'Karan Patel',
                'avatar' => 'assets/storefront/images/avatar/avatar-7.jpg',
                'quote' => 'The catalogue and banners make the store look professional without making us feel like a big marketplace chain.',
                'product_name' => 'Digital Catalogue',
                'product_image' => 'assets/storefront/images/product/product-4.jpg',
                'tag' => 'Merchant Story',
            ],
            [
                'name' => 'Karan Patel',
                'avatar' => 'assets/storefront/images/avatar/avatar-7.jpg',
                'quote' => 'The catalogue and banners make the store look professional without making us feel like a big marketplace chain.',
                'product_name' => 'Digital Catalogue',
                'product_image' => 'assets/storefront/images/product/product-4.jpg',
                'tag' => 'Merchant Story',
            ],
        ];

        return view('storefront.pages.testimonials', [
            'testimonials' => $testimonials,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function faq(): View
    {
        return view('storefront.pages.faq', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function terms(): View
    {
        return view('storefront.pages.terms', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function privacy(): View
    {
        return view('storefront.pages.privacy', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function returns(): View
    {
        return view('storefront.pages.returns', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function shipping(): View
    {
        return view('storefront.pages.shipping', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function contact(): View
    {
        return view('storefront.pages.contact', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function login(Request $request, CheckoutFlowService $checkout, StorefrontCustomerContext $customerContext): View|RedirectResponse
    {
        $checkoutMode = $request->query('from') === 'checkout' && $checkout->hasCartItems($request);

        if ($checkoutMode) {
            $checkout->rememberIntent($request);

            if ($customerContext->user($request) !== null) {
                return redirect()->route(CheckoutFlowService::ADDRESS_ROUTE);
            }
        } elseif ($customerContext->user($request) !== null) {
            return redirect()->route('storefront.account');
        }

        return view('storefront.pages.customer-login', [
            'checkoutMode' => $checkoutMode,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function register(Request $request, CheckoutFlowService $checkout, StorefrontCustomerContext $customerContext): View|RedirectResponse
    {
        $checkoutMode = $request->query('from') === 'checkout' && $checkout->hasCartItems($request);

        if ($checkoutMode) {
            $checkout->rememberIntent($request);

            if ($customerContext->user($request) !== null) {
                return redirect()->route(CheckoutFlowService::ADDRESS_ROUTE);
            }
        } elseif ($customerContext->user($request) !== null) {
            return redirect()->route('storefront.account');
        }

        return view('storefront.pages.customer-register', [
            'checkoutMode' => $checkoutMode,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function forgotPassword(): View
    {
        return view('storefront.pages.customer-forgot-password', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function products(Request $request): View
    {
        $products = $this->productListings->marketplaceProducts();

        return view('storefront.pages.products', [
            'products' => $products,
            'wishlistedProductIds' => $this->wishlistedProductIds($request, $products->items()),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function productDetail(Request $request, ?string $slug = null): View|RedirectResponse
    {
        $detail = $this->productListings->productDetail($slug);

        abort_if($detail === null, 404);

        if ($slug !== null && ($detail['product']['canonical_url'] ?? null) !== null && $request->url() !== $detail['product']['canonical_url']) {
            return redirect()->to($detail['product']['canonical_url'], 301);
        }

        $wishlistProducts = collect([$detail['product']])
            ->merge($detail['relatedProducts'])
            ->all();

        return view('storefront.pages.product-detail', [
            'product' => $detail['product'],
            'relatedProducts' => $detail['relatedProducts'],
            'wishlistedProductIds' => $this->wishlistedProductIds($request, $wishlistProducts),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function productDetailWithCategory(Request $request, string $categoryPath, string $slug): View|RedirectResponse
    {
        $detail = $this->productListings->productDetail($slug);

        abort_if($detail === null, 404);

        $canonicalUrl = (string) ($detail['product']['canonical_url'] ?? route('storefront.product.show', $slug));

        if (trim($categoryPath, '/') !== (string) ($detail['product']['category_path'] ?? '')) {
            return redirect()->to($canonicalUrl, 301);
        }

        $wishlistProducts = collect([$detail['product']])
            ->merge($detail['relatedProducts'])
            ->all();

        return view('storefront.pages.product-detail', [
            'product' => $detail['product'],
            'relatedProducts' => $detail['relatedProducts'],
            'wishlistedProductIds' => $this->wishlistedProductIds($request, $wishlistProducts),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function checkProductDelivery(
        Request $request,
        string $slug,
        ShopDeliveryServiceabilityService $serviceability,
        CustomerLocationService $location,
    ): RedirectResponse|JsonResponse {
        $validator = Validator::make($request->all(), [
            'postal_code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
            ],
        ], [
            'postal_code.required' => 'Please enter your delivery PIN code.',
            'postal_code.regex' => 'Enter a valid 6-digit PIN code.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first('postal_code') ?: 'Enter a valid delivery PIN code.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()
                ->withErrors($validator, 'deliveryCheck')
                ->withInput();
        }

        $product = Product::query()
            ->with(['shop:id,merchant_id,name,status,pincode'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereHas('merchant', fn ($query) => $query->where('status', 'active'))
            ->whereHas('shop', fn ($query) => $query
                ->where('status', 'active')
                ->whereColumn('shops.merchant_id', 'products.merchant_id')
                ->whereHas('merchant', fn ($query) => $query->where('status', 'active')))
            ->firstOrFail();

        $requestedPostalCode = (string) $validator->validated()['postal_code'];
        $result = $serviceability->check($product->shop, $requestedPostalCode);
        $postalCode = $result['destination_postal_code'] ?? trim($requestedPostalCode);
        $cookie = null;

        if (($result['destination_location'] ?? null) !== null) {
            $postalCode = $location->store($request, $postalCode);
            $cookie = cookie(CustomerLocationService::COOKIE_NAME, $postalCode, CustomerLocationService::COOKIE_MINUTES);
        }

        $record = PostalCode::query()
            ->active()
            ->shippingEnabled()
            ->where('postal_code', $postalCode)
            ->orderBy('office_name')
            ->first();

        if (! $result['serviceable']) {
            $payload = [
                'status' => 'blocked',
                'product_slug' => $product->slug,
                'postal_code' => $postalCode,
                'message' => $result['message'] ?: 'Delivery is not available to this PIN code.',
            ];

            $response = $request->expectsJson()
                ? response()->json($payload)
                : back()->with('delivery_check', $payload)->withInput();

            if ($cookie !== null) {
                $response->withCookie($cookie);
            }

            return $response;
        }

        $locationText = collect([$record?->office_name, $record?->district, $record?->state])
            ->filter()
            ->unique()
            ->implode(', ');
        $storeName = $product->shop?->name ?: 'the store';
        $payload = [
            'status' => 'available',
            'product_slug' => $product->slug,
            'postal_code' => $postalCode,
            'message' => trim("Delivery is available to {$postalCode}".($locationText !== '' ? " ({$locationText})" : '').". Estimated date will be confirmed by {$storeName}."),
        ];

        if ($request->expectsJson()) {
            $response = response()->json($payload);
            if ($cookie !== null) {
                $response->withCookie($cookie);
            }

            return $response;
        }

        $response = back()
            ->with('delivery_check', $payload)
            ->withInput();

        if ($cookie !== null) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    public function cart(Request $request, CartPageService $cartPage): View
    {
        $cart = $cartPage->pageData($request);

        return view('storefront.pages.cart', [
            'cart' => $cart,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function category(Request $request, string $slug): View|RedirectResponse
    {
        $category = ProductCategory::query()
            ->with($this->categoryListingRelations())
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        if ($category->parent_id !== null) {
            return redirect()->to($this->urls->category($category), 301);
        }

        return $this->categoryListingView($request, $category);
    }

    public function categoryWithParent(Request $request, string $parentSlug, string $slug): View|RedirectResponse
    {
        $parent = ProductCategory::query()
            ->where('slug', $parentSlug)
            ->where('status', 'active')
            ->firstOrFail();

        $category = ProductCategory::query()
            ->with($this->categoryListingRelations())
            ->where('slug', $slug)
            ->where('status', 'active')
            ->where('parent_id', $parent->getKey())
            ->firstOrFail();

        $requestedPath = $parentSlug.'/'.$slug;

        if (! $this->urls->categoryMatchesPath($category, $requestedPath)) {
            return redirect()->to($this->urls->category($category), 301);
        }

        return $this->categoryListingView($request, $category);
    }

    public function categoryPath(Request $request, string $categoryPath): View|RedirectResponse
    {
        $slugs = collect(explode('/', trim($categoryPath, '/')))
            ->filter()
            ->values();

        abort_if($slugs->isEmpty(), 404);

        $category = ProductCategory::query()
            ->with($this->categoryListingRelations())
            ->where('slug', $slugs->last())
            ->where('status', 'active')
            ->firstOrFail();

        if (! $this->urls->categoryMatchesPath($category, $categoryPath)) {
            return redirect()->to($this->urls->category($category), 301);
        }

        return $this->categoryListingView($request, $category);
    }

    private function categoryListingView(Request $request, ProductCategory $category): View
    {
        $selectedFilters = [
            'attributes' => $request->array('attributes'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'discount_min' => $request->array('discount_min'),
        ];

        return view('storefront.pages.category-products', [
            'category' => $category,
            'childCategories' => $category->children,
            'breadcrumbCategories' => $this->breadcrumbCategories($category),
            'products' => $products = $this->productListings->categoryProducts($category, $selectedFilters),
            'wishlistedProductIds' => $this->wishlistedProductIds($request, $products->items()),
            'attributeFilters' => $this->productListings->categoryAttributeFilters($category),
            'storefrontUrls' => $this->urls,
            'selectedFilters' => $selectedFilters,
            'selectedAttributeFilters' => $selectedFilters['attributes'],
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryListingRelations(): array
    {
        return [
            'parent.parent',
            'children' => fn ($query) => $query
                ->with('parent')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name'),
        ];
    }

    private function breadcrumbCategories(ProductCategory $category): Collection
    {
        $items = collect();
        $current = $category;

        while ($current) {
            $items->prepend($current);
            $current = $current->parent;
        }

        return $items->values();
    }

    /**
     * @param iterable<int, array<string, mixed>> $products
     * @return array<int, int>
     */
    private function wishlistedProductIds(Request $request, iterable $products): array
    {
        $customer = $this->customerContext->customer($request);

        if ($customer === null) {
            return [];
        }

        $productIds = collect($products)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        return WishlistItem::query()
            ->where('customer_id', $customer->getKey())
            ->whereIn('product_id', $productIds->all())
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
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

    private function homepageCategoryCards(): Collection
    {
        return $this->navigation->getHomepageCategories()
            ->values()
            ->map(function (ProductCategory $category, int $index): array {
                $fallbackImage = 'assets/storefront/images/category/cate-'.(($index % NavigationService::HOMEPAGE_CATEGORY_LIMIT) + 1).'.jpg';

                return [
                    'name' => $category->name,
                    'image' => $category->image_path ? 'storage/'.$category->image_path : $fallbackImage,
                    'url' => $this->urls->category($category),
                ];
            });
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
