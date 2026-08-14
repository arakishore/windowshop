<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PostalCode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\PostalCodeServiceabilityService;
use App\Services\Banner\BannerService;
use App\Services\Storefront\CustomerLocationService;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\ProductListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly BannerService $banners,
        private readonly ProductListingService $productListings,
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

    public function stores(): View
    {
        $stores = [
            [
                'name' => 'Green Basket Market',
                'address' => 'Main Road, Near City Centre',
                'area' => 'Grocery & Daily Needs',
                'image' => 'assets/storefront/images/section/store-1.jpg',
                'website_url' => url('/store/green-basket-market'),
            ],
            [
                'name' => 'Style Corner',
                'address' => 'Fashion Street, Central Market',
                'area' => 'Fashion & Lifestyle',
                'image' => 'assets/storefront/images/section/store-2.jpg',
                'website_url' => url('/store/style-corner'),
            ],
            [
                'name' => 'Home Care Essentials',
                'address' => 'Station Road, Local Shopping Lane',
                'area' => 'Home & Living',
                'image' => 'assets/storefront/images/section/store-3.jpg',
                'website_url' => url('/store/home-care-essentials'),
            ],
            [
                'name' => 'Mobile Point',
                'address' => 'Tech Plaza, Market Square',
                'area' => 'Electronics & Accessories',
                'image' => 'assets/storefront/images/section/store-4.jpg',
                'website_url' => url('/store/mobile-point'),
            ],
            [
                'name' => 'Fresh Bloom Florist',
                'address' => 'Garden Lane, Old Market',
                'area' => 'Flowers & Gifts',
                'image' => 'assets/storefront/images/section/store-5.jpg',
                'website_url' => url('/store/fresh-bloom-florist'),
            ],
            [
                'name' => 'Daily Wellness Store',
                'address' => 'Health Street, Community Complex',
                'area' => 'Health & Personal Care',
                'image' => 'assets/storefront/images/section/store-1.jpg',
                'website_url' => url('/store/daily-wellness-store'),
            ],
        ];

        return view('storefront.pages.stores', [
            'stores' => $stores,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
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

    public function login(): View
    {
        return view('storefront.pages.customer-login', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function register(): View
    {
        return view('storefront.pages.customer-register', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function forgotPassword(): View
    {
        return view('storefront.pages.customer-forgot-password', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function products(): View
    {
        return view('storefront.pages.products', [
            'products' => $this->productListings->marketplaceProducts(),
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function productDetail(?string $slug = null): View
    {
        $detail = $this->productListings->productDetail($slug);

        abort_if($detail === null, 404);

        return view('storefront.pages.product-detail', [
            'product' => $detail['product'],
            'relatedProducts' => $detail['relatedProducts'],
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function checkProductDelivery(
        Request $request,
        string $slug,
        PostalCodeServiceabilityService $serviceability,
        CustomerLocationService $location,
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'postal_code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                Rule::exists('postal_codes', 'postal_code')
                    ->where('status', PostalCode::STATUS_ACTIVE)
                    ->where('shipping_enabled', true)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'postal_code.required' => 'Please enter your delivery PIN code.',
            'postal_code.regex' => 'Enter a valid 6-digit PIN code.',
            'postal_code.exists' => 'Delivery is not available for this PIN code yet.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'deliveryCheck')
                ->withInput();
        }

        $product = Product::query()
            ->with(['shop:id,merchant_id,name,status'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereHas('merchant', fn ($query) => $query->where('status', 'active'))
            ->whereHas('shop', fn ($query) => $query
                ->where('status', 'active')
                ->whereColumn('shops.merchant_id', 'products.merchant_id')
                ->whereHas('merchant', fn ($query) => $query->where('status', 'active')))
            ->firstOrFail();

        $postalCode = $location->store($request, (string) $validator->validated()['postal_code']);
        $cookie = cookie(CustomerLocationService::COOKIE_NAME, $postalCode, CustomerLocationService::COOKIE_MINUTES);
        $record = PostalCode::query()
            ->active()
            ->shippingEnabled()
            ->where('postal_code', $postalCode)
            ->orderBy('office_name')
            ->first();
        $result = $serviceability->check($postalCode, (int) $product->merchant_id, (int) $product->shop_id);

        if (! $result['serviceable']) {
            return back()
                ->with('delivery_check', [
                    'status' => 'blocked',
                    'product_slug' => $product->slug,
                    'postal_code' => $postalCode,
                    'message' => $result['reason'] ?: 'Delivery is temporarily unavailable for this PIN code.',
                ])
                ->withInput()
                ->withCookie($cookie);
        }

        $locationText = collect([$record?->office_name, $record?->district, $record?->state])
            ->filter()
            ->unique()
            ->implode(', ');
        $storeName = $product->shop?->name ?: 'the store';

        return back()
            ->with('delivery_check', [
                'status' => 'available',
                'product_slug' => $product->slug,
                'postal_code' => $postalCode,
                'message' => trim("Delivery is available to {$postalCode}".($locationText !== '' ? " ({$locationText})" : '').". Estimated date will be confirmed by {$storeName}."),
            ])
            ->withInput()
            ->withCookie($cookie);
    }

    public function cart(): View
    {
        $cartItems = $this->staticCartItems();
        $totals = $this->staticCartTotals($cartItems);

        return view('storefront.pages.cart', [
            'cartItems' => $cartItems,
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'shipping' => $totals['shipping'],
            'total' => $totals['total'],
            'freeShippingRemaining' => 70.00,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function checkout(): View
    {
        $cartItems = $this->staticCartItems();
        $totals = $this->staticCartTotals($cartItems);

        return view('storefront.pages.checkout', [
            'cartItems' => $cartItems,
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'shipping' => $totals['shipping'],
            'total' => $totals['total'],
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function category(Request $request, string $slug): View
    {
        $category = ProductCategory::query()
            ->with($this->categoryListingRelations())
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        return $this->categoryListingView($request, $category);
    }

    public function categoryWithParent(Request $request, string $parentSlug, string $slug): View
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
            'products' => $this->productListings->categoryProducts($category, $selectedFilters),
            'attributeFilters' => $this->productListings->categoryAttributeFilters($category),
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
                    'url' => route('storefront.category.show', $category->slug),
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

    private function staticCartItems(): array
    {
        return [
            [
                'name' => 'V-neck cotton T-shirt',
                'image' => 'product-3.jpg',
                'price' => 29.99,
                'quantity' => 1,
                'color' => 'Light Gray',
                'size' => 'Small',
            ],
            [
                'name' => 'Square metallic sunglasses',
                'image' => 'product-6.jpg',
                'price' => 69.99,
                'quantity' => 1,
                'color' => 'Charcoal',
                'size' => 'Medium',
            ],
            [
                'name' => 'Oval shoulder bag',
                'image' => 'product-8.jpg',
                'price' => 49.99,
                'quantity' => 1,
                'color' => 'Taupe',
                'size' => 'One Size',
            ],
        ];
    }

    private function staticCartTotals(array $cartItems): array
    {
        $subtotal = collect($cartItems)->sum(fn (array $item): float => $item['price'] * $item['quantity']);
        $discount = 20.00;
        $shipping = 0.00;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $subtotal - $discount + $shipping,
        ];
    }
}
