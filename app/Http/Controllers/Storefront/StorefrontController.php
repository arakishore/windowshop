<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Enums\BannerPosition;
use App\Models\CmsPage;
use App\Models\PostalCode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\Shop;
use App\Models\ShopPage;
use App\Models\ShopAudience;
use App\Models\WishlistItem;
use App\Services\Banner\BannerService;
use App\Services\Cart\CartPageService;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Delivery\ShopDeliveryServiceabilityService;
use App\Services\Merchant\ShopPageContent;
use App\Services\Storefront\CustomerLocationService;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\ProductLocationSorter;
use App\Services\Storefront\ProductListingService;
use App\Services\Storefront\ShopOfferProductService;
use App\Services\Storefront\ShopPromotionPresenter;
use App\Services\Storefront\StorefrontCustomerContext;
use App\Services\Storefront\StorefrontCountryResolver;
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
        private readonly ShopPromotionPresenter $shopPromotions,
        private readonly ShopOfferProductService $offerProducts,
        private readonly ShopPageContent $pageContent,
        private readonly StorefrontCustomerContext $customerContext,
        private readonly StorefrontUrlService $urls,
    ) {}

    public function home(Request $request, CustomerLocationService $location): View
    {
        $postalCode = $location->postalCode($request);
        $postalCodeRecord = $postalCode ? $location->postalCodeRecord($postalCode) : null;
        $district = trim((string) ($postalCodeRecord?->district ?? ''));
        $state = trim((string) ($postalCodeRecord?->state ?? ''));
        $nearbyStoresQuery = $this->storeDiscoveryBaseQuery();
        $this->applyStoreLocationScope($nearbyStoresQuery, $district, $state);
        $eligibleShopIds = (clone $nearbyStoresQuery)->reorder()->pluck('shops.id')->all();
        $offerCounts = $this->shopPromotions->currentOfferCountsForShopIds($eligibleShopIds);
        $offerShopIds = array_keys($offerCounts);

        if ($offerShopIds !== []) {
            $placeholders = implode(',', array_fill(0, count($offerShopIds), '?'));
            $nearbyStoresQuery->orderByRaw("CASE WHEN shops.id IN ({$placeholders}) THEN 0 ELSE 1 END", $offerShopIds);
        }

        $nearbyStoreModels = $nearbyStoresQuery
            ->orderByDesc('shops.created_at')
            ->orderByDesc('shops.id')
            ->limit(6)
            ->get();
        $nearbyOfferShop = $nearbyStoreModels->first(fn (Shop $shop): bool => isset($offerCounts[(int) $shop->getKey()]));
        $nearbyStores = $nearbyStoreModels
            ->map(fn (Shop $shop): array => $this->storeCardData($shop, $offerShopIds, $offerCounts));
        $nearbyOffers = $nearbyOfferShop instanceof Shop
            ? $this->shopPromotions->currentForShop($nearbyOfferShop, 6)
            : collect();
        $newArrivalProducts = $this->productListings->newestProductsForShopIds($eligibleShopIds);

        return view('storefront.pages.home', [
            'heroBanners' => $this->banners->getMarketplaceHeroBanners(),
            'heroCity' => mb_strtoupper($district !== '' ? $district : 'NASHIK'),
            'homepageCategories' => $this->homepageCategoryCards(),
            'nearbyStores' => $nearbyStores,
            'nearbyStoresLocationLabel' => $district !== '' ? $district : $postalCode,
            'nearbyOfferShop' => $nearbyOfferShop,
            'nearbyOffers' => $nearbyOffers,
            'nearbyFeaturedOffer' => $nearbyOffers->first(fn (array $offer): bool => ! empty($offer['promotional_image_url'])),
            'newArrivalProducts' => $newArrivalProducts,
            'newArrivalWishlistedProductIds' => $this->wishlistedProductIds($request, $newArrivalProducts),
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
            ->withQueryString();
        $offerShopIds = $this->shopPromotions->currentOfferShopIds(
            $stores->getCollection()->pluck('id')->all()
        );
        $stores = $stores->through(fn (Shop $shop): array => $this->storeCardData($shop, $offerShopIds));

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

    private function storeCardData(Shop $shop, array $offerShopIds = [], array $offerCounts = []): array
    {
        $imagePath = $shop->banner_path ?: $shop->logo_path;
        $storeUrl = route('storefront.stores.show', $shop->slug);
        $fullAddress = collect([
            $shop->address_line_1,
            $shop->address_line_2,
            $shop->landmark,
            $shop->city?->name,
            $shop->pincode,
        ])->filter()->implode(', ');
        $locationLabel = collect([
            $shop->landmark,
            $shop->city?->name,
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
            'location_label' => $locationLabel ?: ($fullAddress ?: null),
            'maps_url' => $mapsUrl,
            'shop_type' => $shop->rootProductCategory?->name,
            'audiences' => $shop->audiences->pluck('name')->values()->all(),
            'has_offers' => in_array((int) $shop->getKey(), $offerShopIds, true),
            'offer_count' => (int) ($offerCounts[(int) $shop->getKey()] ?? 0),
            'image_is_placeholder' => $imagePath === null,
            'image' => $imagePath ? 'storage/'.$imagePath : 'assets/storefront/images/no-image-icon.png',
            'logo' => $shop->logo_path ? 'storage/'.$shop->logo_path : null,
            'initials' => $this->storeInitials($shop->name),
            'store_url' => $storeUrl,
        ];
    }

    private function similarShops(Shop $shop, int $limit = 4): Collection
    {
        $categoryId = $shop->root_product_category_id ? (int) $shop->root_product_category_id : null;
        $audienceIds = $shop->audiences
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
        $matches = collect();

        $addMatches = function ($query) use (&$matches, $limit): void {
            if ($matches->count() >= $limit) {
                return;
            }

            $existingIds = $matches->pluck('id')->all();

            if ($existingIds !== []) {
                $query->whereNotIn('id', $existingIds);
            }

            $shops = $query
                ->orderBy('name')
                ->limit($limit - $matches->count())
                ->get();

            $matches = $matches->concat($shops);
        };

        $baseQuery = fn () => $this->storeDiscoveryBaseQuery()
            ->whereKeyNot($shop->getKey());

        if ($categoryId !== null && $audienceIds !== []) {
            $addMatches($baseQuery()
                ->where('root_product_category_id', $categoryId)
                ->whereHas('audiences', fn ($query) => $query->whereIn('shop_audiences.id', $audienceIds)));
        }

        if ($categoryId !== null) {
            $addMatches($baseQuery()
                ->where('root_product_category_id', $categoryId));
        }

        if ($audienceIds !== []) {
            $addMatches($baseQuery()
                ->whereHas('audiences', fn ($query) => $query->whereIn('shop_audiences.id', $audienceIds)));
        }

        $addMatches($baseQuery());

        $matches = $matches->take($limit)->values();
        $offerShopIds = $this->shopPromotions->currentOfferShopIds($matches->pluck('id')->all());

        return $matches
            ->map(fn (Shop $shop): array => $this->storeCardData($shop, $offerShopIds))
            ->values();
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
        return $this->marketplaceCmsPage('terms', 'storefront.pages.terms');
    }

    public function privacy(): View
    {
        return $this->marketplaceCmsPage('privacy', 'storefront.pages.privacy');
    }

    public function returns(): View
    {
        return $this->marketplaceCmsPage('return_refund', 'storefront.pages.returns');
    }

    public function shipping(): View
    {
        return $this->marketplaceCmsPage('shipping', 'storefront.pages.shipping');
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

    public function register(Request $request, CheckoutFlowService $checkout, StorefrontCustomerContext $customerContext, StorefrontCountryResolver $countries): View|RedirectResponse
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
            'defaultCountryCode' => $countries->defaultCountryCode(),
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
        $selectedFilters = $this->selectedProductFilters($request);

        return view('storefront.pages.category-products', [
            'category' => $category,
            'childCategories' => $category->children,
            'breadcrumbCategories' => $this->breadcrumbCategories($category),
            'products' => $products = $this->productListings->categoryProducts($category, $selectedFilters),
            'wishlistedProductIds' => $this->wishlistedProductIds($request, $products->items()),
            'attributeFilters' => $this->productListings->categoryAttributeFilters($category),
            'shopFilterOptions' => $this->productListings->categoryShopFilters($category),
            'storefrontUrls' => $this->urls,
            'selectedFilters' => $selectedFilters,
            'selectedAttributeFilters' => $selectedFilters['attributes'],
            'selectedCategoryFilters' => $selectedFilters['categories'],
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    private function selectedProductFilters(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'shops' => $request->array('shops'),
            'categories' => $request->array('categories'),
            'sort' => $request->input('sort'),
            'attributes' => $request->array('attributes'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'discount_min' => $request->array('discount_min'),
        ];
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

    public function store(Request $request, string $slug): View
    {
        return $this->shopPageView($request, $slug, true);
    }

    public function storeProfile(Request $request, string $slug): View
    {
        return $this->shopPageView($request, $slug, false);
    }

    public function storeCmsPage(string $slug, string $pageSlug): View
    {
        $shop = $this->activeShopBySlug($slug)->load([
            'rootProductCategory:id,name,slug',
            'audiences:id,name,slug',
            'city:id,name',
            'state:id,name',
            'country:id,name',
        ]);
        $page = $shop->pages()
            ->where('slug', $pageSlug)
            ->where('status', ShopPage::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();
        $heroProducts = $this->productListings->shopProducts($shop, [], 5);
        $shopProfile = $this->shopProfileData($shop);
        $shopProfile['product_count'] = $heroProducts->total();

        return view('storefront.pages.store-cms-page', [
            'shop' => $shop,
            'page' => $page,
            'shopProfile' => $shopProfile,
            'heroProducts' => $heroProducts,
            'heroBanners' => $this->banners->getStoreBanners((int) $shop->getKey(), BannerPosition::STORE_HERO),
            'shopLocation' => $this->shopLocationData($shop),
            'shopWhatsappUrl' => $this->productListings->shopWhatsappUrl($shop, "Hello {$shop->name}!"),
            'hasShopOffers' => $this->shopPromotions->currentForShop($shop, 1)->isNotEmpty(),
            'shopFooterPages' => $this->publishedStandardPages($shop),
            'storefrontShop' => $shop,
            'storefrontNavigationCategories' => $this->navigation->getMerchantCategories($shop),
        ]);
    }

    private function shopPageView(Request $request, string $slug, bool $merchantStorefront): View
    {
        $shop = $this->activeShopBySlug($slug)->load([
            'rootProductCategory:id,name,slug',
            'audiences:id,name,slug',
            'city:id,name',
            'state:id,name',
            'country:id,name',
            'merchant:id,status',
        ]);
        $selectedFilters = $this->selectedProductFilters($request);
        $products = $this->productListings->shopProducts($shop, $selectedFilters);
        $shopProfile = $this->shopProfileData($shop);
        $shopProfile['product_count'] = $products->total();
        $offerProductIds = $this->offerProducts->productIdsForShop($shop);
        $offerPreview = $this->productListings->shopProductPreviewByIds($shop, $offerProductIds, ProductListingService::PER_PAGE);

        return view('storefront.pages.store-profile', [
            'shop' => $shop,
            'shopProfile' => $shopProfile,
            'shopWhatsappUrl' => $this->productListings->shopWhatsappUrl($shop, "Hello {$shop->name}!"),
            'products' => $products,
            'wishlistedProductIds' => $this->wishlistedProductIds($request, $products->items()),
            'heroBanners' => $this->banners->getStoreBanners((int) $shop->getKey(), BannerPosition::STORE_HERO),
            'middleBanners' => $this->banners->getStoreBanners((int) $shop->getKey(), BannerPosition::STORE_MIDDLE),
            'shopPromotions' => $this->shopPromotions->currentForShop($shop),
            'offerProducts' => $offerPreview['products'],
            'offerProductsTotal' => $offerPreview['total'],
            'offerProductsUrl' => route('storefront.stores.offers', $shop->slug),
            'similarShops' => $this->similarShops($shop),
            'shopLocation' => $this->shopLocationData($shop),
            'shopFooterPages' => $this->publishedStandardPages($shop),
            'categoryFilterOptions' => $this->productListings->shopCategoryFilters($shop),
            'attributeFilters' => $this->productListings->shopAttributeFilters($shop),
            'selectedFilters' => $selectedFilters,
            'selectedAttributeFilters' => $selectedFilters['attributes'],
            'selectedCategoryFilters' => $selectedFilters['categories'],
            'showShopFilter' => false,
            'storefrontShop' => $merchantStorefront ? $shop : null,
            'storefrontNavigationCategories' => $merchantStorefront
                ? $this->navigation->getMerchantCategories($shop)
                : $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function storeOfferProducts(Request $request, string $slug): View
    {
        $shop = $this->activeShopBySlug($slug)->load([
            'rootProductCategory:id,name,slug',
            'audiences:id,name,slug',
            'city:id,name',
            'state:id,name',
            'country:id,name',
            'merchant:id,status',
        ]);
        $selectedFilters = $this->selectedProductFilters($request);
        $promotionIdentifier = trim((string) $request->query('promotion', ''));
        $selectedPromotion = $promotionIdentifier !== ''
            ? $this->offerProducts->currentPromotionForShop($shop, $promotionIdentifier)
            : null;
        $invalidPromotionFilter = $promotionIdentifier !== '' && $selectedPromotion === null;
        $offerProductIds = $selectedPromotion instanceof Promotion
            ? $this->offerProducts->productIdsForPromotion($selectedPromotion, $shop)
            : ($invalidPromotionFilter ? [] : $this->offerProducts->productIdsForShop($shop));
        $products = $this->productListings->shopProductsByIds($shop, $offerProductIds, $selectedFilters);
        $shopProfile = $this->shopProfileData($shop);
        $shopProfile['product_count'] = $products->total();
        $selectedOffer = $selectedPromotion instanceof Promotion
            ? $this->shopPromotions->card($selectedPromotion, $shop)
            : null;

        return view('storefront.pages.store-offers', [
            'shop' => $shop,
            'shopProfile' => $shopProfile,
            'products' => $products,
            'selectedOffer' => $selectedOffer,
            'invalidPromotionFilter' => $invalidPromotionFilter,
            'wishlistedProductIds' => $this->wishlistedProductIds($request, $products->items()),
            'categoryFilterOptions' => $this->productListings->shopCategoryFilters($shop),
            'attributeFilters' => $this->productListings->shopAttributeFilters($shop),
            'selectedFilters' => $selectedFilters,
            'selectedAttributeFilters' => $selectedFilters['attributes'],
            'selectedCategoryFilters' => $selectedFilters['categories'],
            'showShopFilter' => false,
            'storefrontShop' => null,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
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
            ->whereHas('merchant', fn ($query) => $query->where('status', 'active'))
            ->firstOrFail();
    }

    private function publishedStandardPages(Shop $shop): Collection
    {
        return $shop->pages()
            ->where('page_type', ShopPage::TYPE_STANDARD)
            ->where('status', ShopPage::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereIn('page_key', ['about', 'privacy', 'terms', 'policies'])
            ->get(['page_key', 'slug', 'title'])
            ->keyBy('page_key');
    }

    private function shopProfileData(Shop $shop): array
    {
        $address = $this->shopAddress($shop);

        return [
            'name' => $shop->name,
            'initials' => $this->storeInitials($shop->name),
            'logo' => $shop->logo_path ? 'storage/'.$shop->logo_path : null,
            'cover' => $shop->banner_path ? 'storage/'.$shop->banner_path : null,
            'shop_type' => $shop->rootProductCategory?->name,
            'audiences' => $shop->audiences->pluck('name')->values()->all(),
            'address' => $address,
            'maps_url' => $this->shopMapsUrl($shop, $address),
            'description' => $shop->description ?: $shop->short_description,
            'website_url' => route('storefront.store.show', $shop->slug),
            'product_count' => 0,
        ];
    }

    private function shopLocationData(Shop $shop): array
    {
        $address = $this->shopFullAddress($shop);
        $latitude = is_numeric($shop->latitude) ? (float) $shop->latitude : null;
        $longitude = is_numeric($shop->longitude) ? (float) $shop->longitude : null;
        $hasCoordinates = $latitude !== null && $longitude !== null;
        $mapLatitude = $latitude;
        $mapLongitude = $longitude;
        $mapPrecision = $hasCoordinates ? 'shop' : null;
        $showMarker = $hasCoordinates;
        $zoom = 15;

        if (! $hasCoordinates) {
            $postalCoordinates = $this->postalCodeCoordinates($shop);

            if ($postalCoordinates !== null) {
                $mapLatitude = $postalCoordinates['latitude'];
                $mapLongitude = $postalCoordinates['longitude'];
                $mapPrecision = 'postal_code';
                $zoom = 13;
            } else {
                $cityCoordinates = $this->cityCoordinates($shop);

                if ($cityCoordinates !== null) {
                    $mapLatitude = $cityCoordinates['latitude'];
                    $mapLongitude = $cityCoordinates['longitude'];
                    $mapPrecision = 'city';
                    $zoom = 11;
                }
            }
        }

        return [
            'name' => $shop->name,
            'address' => $address,
            'area' => $shop->landmark,
            'city' => $shop->city?->name,
            'state' => $shop->state?->name,
            'pincode' => $shop->pincode,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'has_coordinates' => $hasCoordinates,
            'map_latitude' => $mapLatitude,
            'map_longitude' => $mapLongitude,
            'map_available' => $mapLatitude !== null && $mapLongitude !== null,
            'map_precision' => $mapPrecision,
            'show_marker' => $showMarker,
            'zoom' => $zoom,
            'directions_url' => $this->shopDirectionsUrl($shop, $address),
        ];
    }

    private function postalCodeCoordinates(Shop $shop): ?array
    {
        $pincode = trim((string) $shop->pincode);

        if ($pincode === '') {
            return null;
        }

        $postalCode = PostalCode::query()
            ->active()
            ->where('postal_code', $pincode)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('shipping_enabled')
            ->orderBy('office_name')
            ->first(['latitude', 'longitude']);

        if (! $postalCode || ! is_numeric($postalCode->latitude) || ! is_numeric($postalCode->longitude)) {
            return null;
        }

        return [
            'latitude' => (float) $postalCode->latitude,
            'longitude' => (float) $postalCode->longitude,
        ];
    }

    private function cityCoordinates(Shop $shop): ?array
    {
        $city = trim((string) $shop->city?->name);
        $state = trim((string) $shop->state?->name);

        if ($city === '') {
            return null;
        }

        $coordinates = PostalCode::query()
            ->active()
            ->where('district', $city)
            ->when($state !== '', fn ($query) => $query->where('state', $state))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('AVG(latitude) as latitude, AVG(longitude) as longitude')
            ->first();

        if (! $coordinates || ! is_numeric($coordinates->latitude) || ! is_numeric($coordinates->longitude)) {
            return null;
        }

        return [
            'latitude' => (float) $coordinates->latitude,
            'longitude' => (float) $coordinates->longitude,
        ];
    }

    private function shopAddress(Shop $shop): string
    {
        return collect([
            $shop->address_line_1,
            $shop->address_line_2,
            $shop->landmark,
            $shop->city?->name,
            $shop->pincode,
        ])->filter()->implode(', ');
    }

    private function shopFullAddress(Shop $shop): string
    {
        return collect([
            $shop->address_line_1,
            $shop->address_line_2,
            $shop->landmark,
            $shop->city?->name,
            $shop->state?->name,
            $shop->pincode,
            $shop->country?->name,
        ])->filter()->implode(', ');
    }

    private function shopDirectionsUrl(Shop $shop, string $address): ?string
    {
        $latitude = is_numeric($shop->latitude) ? (float) $shop->latitude : null;
        $longitude = is_numeric($shop->longitude) ? (float) $shop->longitude : null;

        if ($latitude !== null && $longitude !== null) {
            return "https://www.google.com/maps/dir/?api=1&destination={$latitude},{$longitude}";
        }

        if ($address !== '') {
            return 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($address);
        }

        return null;
    }

    private function shopMapsUrl(Shop $shop, string $address): ?string
    {
        $latitude = is_numeric($shop->latitude) ? (float) $shop->latitude : null;
        $longitude = is_numeric($shop->longitude) ? (float) $shop->longitude : null;

        if ($latitude !== null && $longitude !== null) {
            return "https://www.google.com/maps?q={$latitude},{$longitude}";
        }

        $searchQuery = collect([
            $shop->name,
            $address,
            $shop->state?->name,
            $shop->country?->name,
        ])->filter()->implode(', ');

        return $searchQuery !== ''
            ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($searchQuery)
            : null;
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

    private function marketplaceCmsPage(string $pageKey, string $fallbackView): View
    {
        $navigation = $this->navigation->getMarketplaceCategories();
        $page = CmsPage::query()
            ->where('page_type', CmsPage::TYPE_STANDARD)
            ->where('page_key', $pageKey)
            ->where('status', CmsPage::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (! $page) {
            return view($fallbackView, ['storefrontNavigationCategories' => $navigation]);
        }

        return view('storefront.pages.marketplace-cms-page', [
            'page' => $page,
            'pageBody' => $this->pageContent->render($page->body),
            'storefrontNavigationCategories' => $navigation,
        ]);
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
