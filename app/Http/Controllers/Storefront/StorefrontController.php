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

    public function products(): View
    {
        $products = [
            ['name' => 'Lyocell wrap top', 'brand' => 'Louis Vuitton', 'image' => 'product-1.jpg', 'hover_image' => 'product-1_2.jpg', 'price' => '$69,99', 'old_price' => '$99,99', 'badge' => 'NEW', 'badge_class' => 'new'],
            ['name' => 'Buttons cotton top', 'brand' => 'Nike', 'image' => 'product-2.jpg', 'hover_image' => 'product-2_2.jpg', 'price' => '$29,99', 'old_price' => '$49,99', 'badge' => '-25%', 'badge_class' => 'sale'],
            ['name' => 'Wool Midi Coat', 'brand' => 'Zara', 'image' => 'product-3.jpg', 'hover_image' => 'product-3_2.jpg', 'price' => '$15,99', 'old_price' => '$25,99', 'badge' => 'NEW', 'badge_class' => 'new'],
            ['name' => 'Linen slim-fit shirt', 'brand' => 'Adidas', 'image' => 'product-4.jpg', 'hover_image' => 'product-4_2.jpg', 'price' => '$45,99', 'old_price' => '$79,99', 'badge' => null, 'badge_class' => null],
            ['name' => 'Ribbed knit top', 'brand' => 'Gucci', 'image' => 'product-5.jpg', 'hover_image' => 'product-5_2.jpg', 'price' => '$39,99', 'old_price' => '$59,99', 'badge' => 'NEW', 'badge_class' => 'new'],
            ['name' => 'Oversized denim jacket', 'brand' => 'Hermes', 'image' => 'product-6.jpg', 'hover_image' => 'product-6_2.jpg', 'price' => '$89,99', 'old_price' => '$119,99', 'badge' => '-15%', 'badge_class' => 'sale'],
            ['name' => 'Leather shopper bag with stitching', 'brand' => 'Gucci', 'image' => 'product-7.jpg', 'hover_image' => 'product-7_2.jpg', 'price' => '$22,99', 'old_price' => '$39,99', 'badge' => null, 'badge_class' => null],
            ['name' => 'Oval shoulder bag', 'brand' => 'Adidas', 'image' => 'product-8.jpg', 'hover_image' => 'product-8_2.jpg', 'price' => '$67,99', 'old_price' => '$99,99', 'badge' => '-25%', 'badge_class' => 'sale'],
            ['name' => 'V-neck cotton t-shirt', 'brand' => 'Nike', 'image' => 'product-9.jpg', 'hover_image' => 'product-9_2.jpg', 'price' => '$12,99', 'old_price' => '$21,99', 'badge' => 'NEW', 'badge_class' => 'new'],
            ['name' => 'Relaxed fit overshirt', 'brand' => 'Zara', 'image' => 'product-10.jpg', 'hover_image' => 'product-10.jpg', 'price' => '$52,99', 'old_price' => '$74,99', 'badge' => null, 'badge_class' => null],
            ['name' => 'Soft everyday sneaker', 'brand' => 'Adidas', 'image' => 'product-11.jpg', 'hover_image' => 'product-11.jpg', 'price' => '$76,99', 'old_price' => '$98,99', 'badge' => 'NEW', 'badge_class' => 'new'],
            ['name' => 'Classic casual jacket', 'brand' => 'Hermes', 'image' => 'product-12.jpg', 'hover_image' => 'product-12_2.jpg', 'price' => '$99,99', 'old_price' => '$129,99', 'badge' => '-20%', 'badge_class' => 'sale'],
        ];

        return view('storefront.pages.products', [
            'products' => $products,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
    }

    public function productDetail(): View
    {
        $product = [
            'name' => 'Lyocell wrap top',
            'category' => 'Fashion & Lifestyle',
            'store' => 'Style Corner',
            'price' => '$79.99',
            'old_price' => '$98.99',
            'discount' => '-25%',
            'sku' => 'WS-STYLE-001',
            'reviews' => '134 reviews',
            'sold_text' => '18 sold in last 32 hours',
            'viewing_text' => '28 people are viewing this right now',
            'description' => 'A catalogue-ready local shop product with soft fabric, easy styling, and clean storefront presentation for customers browsing before they visit.',
            'images' => [
                'detail-1.jpg',
                'detail-1_2.jpg',
                'detail-1_3.jpg',
                'detail-1_4.jpg',
                'detail-1_5.jpg',
                'detail-1_6.jpg',
                'detail-1_7.jpg',
                'detail-1_8.jpg',
            ],
            'colors' => [
                ['name' => 'Green', 'image' => 'img_square/detail-1_2.jpg', 'color' => 'green'],
                ['name' => 'Gray', 'image' => 'img_square/detail-1_5.jpg', 'color' => 'gray'],
                ['name' => 'Black', 'image' => 'img_square/detail-1_7.jpg', 'color' => 'black'],
            ],
            'sizes' => ['S', 'M', 'L', 'XL'],
        ];

        $relatedProducts = [
            ['name' => 'Buttons cotton top', 'image' => 'product-2.jpg', 'hover_image' => 'product-2_2.jpg', 'price' => '$29,99', 'old_price' => '$49,99', 'badge' => '-25%'],
            ['name' => 'Wool Midi Coat', 'image' => 'product-3.jpg', 'hover_image' => 'product-3_2.jpg', 'price' => '$15,99', 'old_price' => '$25,99', 'badge' => 'NEW'],
            ['name' => 'Linen slim-fit shirt', 'image' => 'product-4.jpg', 'hover_image' => 'product-4_2.jpg', 'price' => '$45,99', 'old_price' => '$79,99', 'badge' => null],
            ['name' => 'Ribbed knit top', 'image' => 'product-5.jpg', 'hover_image' => 'product-5_2.jpg', 'price' => '$39,99', 'old_price' => '$59,99', 'badge' => 'NEW'],
        ];

        return view('storefront.pages.product-detail', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ]);
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
