<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\MerchantProfile;
use App\Models\PostalCode;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductCategory;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductImage;
use App\Models\ProductReturnPolicy;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Merchant\ShopSettingsService;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use App\Services\Storefront\CustomerLocationService;
use App\Services\Storefront\StorefrontUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontProductListingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->currencySetting('symbol', 'INR ');
        $this->currencySetting('decimal_places', '2', AdminSetting::TYPE_INTEGER);
        $this->currencySetting('thousands_separator', ',');
        $this->currencySetting('decimal_separator', '.');
        $this->currencySetting('symbol_position', 'before');
    }

    public function test_products_page_renders_dynamic_storefront_products_only(): void
    {
        $fixture = $this->fixture();
        $visible = $this->product($fixture, 'Visible Linen Shirt');
        $this->variant($visible, mrp: '999.00', sellingPrice: '699.00');
        $this->image($visible, 'products/visible-shirt/thumb.webp');

        $draft = $this->product($fixture, 'Draft Product', status: 'draft');
        $this->variant($draft);

        $inactiveShop = $this->shop($fixture['merchant'], $fixture['root'], 'Inactive Shop', 'inactive');
        $inactiveShopProduct = $this->product([...$fixture, 'shop' => $inactiveShop], 'Inactive Shop Product');
        $this->variant($inactiveShopProduct);

        $suspendedMerchant = $this->merchant('Suspended Merchant', 'suspended');
        $suspendedShop = $this->shop($suspendedMerchant, $fixture['root'], 'Suspended Merchant Shop');
        $suspendedProduct = $this->product([...$fixture, 'merchant' => $suspendedMerchant, 'shop' => $suspendedShop], 'Suspended Merchant Product');
        $this->variant($suspendedProduct);

        $content = $this->get(route('storefront.products'))
            ->assertOk()
            ->assertSee('Visible Linen Shirt')
            ->assertSee('INR 699.00')
            ->assertSee('INR 999.00')
            ->assertSee('-30%')
            ->assertSee('/storage/products/visible-shirt/thumb.webp', false)
            ->assertDontSee('Draft Product')
            ->assertDontSee('Inactive Shop Product')
            ->assertDontSee('Suspended Merchant Product')
            ->assertDontSee($fixture['shop']->name)
            ->getContent();

        $listing = $this->productsListingSection($content);
        $this->assertStringNotContainsString('icon-Star', $listing);
        $this->assertStringNotContainsString('product-color_list', $listing);
    }

    public function test_products_without_discount_hide_mrp_and_discount_badge(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Full Price Product');
        $this->variant($product, mrp: '500.00', sellingPrice: '500.00');

        $response = $this->get(route('storefront.products'))->assertOk();

        $section = $this->productCardSection($response->getContent(), 'Full Price Product');
        $this->assertStringContainsString('INR 500.00', $section);
        $this->assertStringNotContainsString('price-old', $section);
        $this->assertStringNotContainsString('product-badge_item', $section);
    }

    public function test_products_page_uses_fallback_image_when_primary_file_is_missing(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Missing Image Product');
        $this->variant($product);
        $image = ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'image_path' => 'products/missing/original.webp',
            'thumbnail_path' => 'products/missing/thumb.webp',
            'is_primary' => true,
            'status' => 'active',
        ]);
        $product->forceFill(['primary_image_id' => $image->getKey()])->save();

        $this->get(route('storefront.products'))
            ->assertOk()
            ->assertSee('Missing Image Product')
            ->assertSee('assets/storefront/images/no-image-icon.png', false)
            ->assertDontSee('/storage/products/missing/thumb.webp', false);
    }

    public function test_products_page_paginates_twelve_products_and_preserves_query_string(): void
    {
        $fixture = $this->fixture();

        foreach (range(1, 13) as $index) {
            $product = $this->product($fixture, 'Paged Product '.$index, overrides: ['sort_order' => $index]);
            $this->variant($product);
        }

        $response = $this->get(route('storefront.products', ['filter' => 'kept']))->assertOk();

        $response->assertSee('Paged Product 1')
            ->assertSee('Paged Product 12')
            ->assertDontSee('Paged Product 13')
            ->assertSee('page=2', false)
            ->assertSee('filter=kept', false);
    }

    public function test_products_page_shows_empty_state(): void
    {
        $this->get(route('storefront.products'))
            ->assertOk()
            ->assertSee('No products found.');
    }

    public function test_product_cards_link_to_dynamic_product_detail_page(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Linked Product');
        $this->variant($product);

        $this->get(route('storefront.products'))
            ->assertOk()
            ->assertSee($this->productUrl($product), false);
    }

    public function test_product_detail_page_renders_live_product_data_and_seo(): void
    {
        $fixture = $this->fixture();
        $fixture['root']->forceFill(['name' => 'Apparel'])->save();
        $fixture['category']->forceFill(['name' => 'Women'])->save();
        $fixture['category'] = ProductCategory::query()->create([
            'parent_id' => $fixture['category']->getKey(),
            'name' => 'T-Shirts',
            'slug' => 'women-t-shirts-'.Str::random(8),
            'status' => 'active',
        ]);
        $fixture['shop']->forceFill([
            'whatsapp_number' => '9870035848',
        ])->save();
        DB::table('system_settings')->insert([
            'key' => 'marketplace_name',
            'label' => 'Marketplace Name',
            'value' => 'LocalHyper',
            'value_type' => 'string',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fixture['category']->forceFill([
            'product_disclaimer' => 'Category fit and color disclaimer.',
        ])->save();
        $product = $this->product($fixture, 'Dynamic Cotton Shirt', overrides: [
            'availability_status_id' => ProductAvailabilityStatus::query()->create([
                'uuid' => (string) Str::uuid(),
                'merchant_id' => $fixture['merchant']->getKey(),
                'code' => 'READY_NOW',
                'name' => 'Ready Now',
                'customer_description' => 'This product is ready at the shop.',
                'purchase_allowed' => true,
                'badge_type' => ProductAvailabilityStatus::BADGE_SUCCESS,
                'status' => ProductAvailabilityStatus::STATUS_ACTIVE,
            ])->getKey(),
            'short_description' => 'Short cotton shirt summary.',
            'description' => 'Full cotton shirt product description from the database.',
            'meta_title' => 'Cotton Shirt Detail',
            'meta_description' => 'Cotton shirt meta description',
        ]);
        $variant = $this->variant($product, mrp: '1000.00', sellingPrice: '750.00');
        $this->image($product, 'products/dynamic-shirt/thumb.webp');
        $colorGroup = $this->attributeGroup('Color', ['Black']);
        $black = $colorGroup->values->firstWhere('code', 'black');
        $black->forceFill(['swatch_hex' => '#111111'])->save();
        ProductVariantAttribute::query()->create([
            'product_variant_id' => $variant->getKey(),
            'product_attribute_group_id' => $colorGroup->getKey(),
            'product_attribute_group_value_id' => $black->getKey(),
        ]);
        $sizeGroup = $this->attributeGroup('Size', ['M', 'L']);
        $medium = $sizeGroup->values->firstWhere('code', 'm');
        ProductVariantAttribute::query()->create([
            'product_variant_id' => $variant->getKey(),
            'product_attribute_group_id' => $sizeGroup->getKey(),
            'product_attribute_group_value_id' => $medium->getKey(),
        ]);
        $largeVariant = $this->variant($product, mrp: '1100.00', sellingPrice: '850.00');
        $largeVariant->forceFill([
            'is_default' => false,
            'sort_order' => 1,
        ])->save();
        $large = $sizeGroup->values->firstWhere('code', 'l');
        ProductVariantAttribute::query()->create([
            'product_variant_id' => $largeVariant->getKey(),
            'product_attribute_group_id' => $sizeGroup->getKey(),
            'product_attribute_group_value_id' => $large->getKey(),
        ]);
        $materialGroup = $this->attributeGroup('Material', ['Cotton']);
        $product->attributes()->create([
            'product_attribute_group_id' => $materialGroup->getKey(),
            'product_attribute_group_value_id' => $materialGroup->values->firstWhere('code', 'cotton')->getKey(),
        ]);

        $related = $this->product($fixture, 'Related Cotton Shirt');
        $this->variant($related);

        $canonicalUrl = $this->productUrl($product);

        $content = $this->get($canonicalUrl)
            ->assertOk()
            ->assertSee('Dynamic Cotton Shirt')
            ->assertSee('Full cotton shirt product description from the database.')
            ->assertSee('product-heading-row', false)
            ->assertSee('product-wishlist-btn', false)
            ->assertSee('Reviews coming soon')
            ->assertDontSee('Available from local shop')
            ->assertSee($fixture['shop']->name)
            ->assertSee($fixture['category']->name)
            ->assertSee('INR 750.00')
            ->assertSee('INR 1,000.00')
            ->assertSee('-25%')
            ->assertSee('Ready Now')
            ->assertSee('This product is ready at the shop.')
            ->assertSee('/storage/products/dynamic-shirt/thumb.webp', false)
            ->assertSee('data-product-delivery-form', false)
            ->assertSee(route('storefront.product.delivery-check', $product->slug), false)
            ->assertSee('data-color="Black"', false)
            ->assertSee('background: #111111', false)
            ->assertDontSee('data-color="green"', false)
            ->assertSee('Size:')
            ->assertSee('data-size="M"', false)
            ->assertSee('data-price="750"', false)
            ->assertSee('data-size="L"', false)
            ->assertSee('data-price="850"', false)
            ->assertSee('sticky-price-add', false)
            ->assertSee('Size Guide')
            ->assertSee('Women T-Shirts Size Chart')
            ->assertSee('assets/storefront/images/size-guides/women-tshirts-size-guide.png', false)
            ->assertSee('Product Details')
            ->assertSee('Material')
            ->assertSee('Cotton')
            ->assertSee('Product Disclaimer')
            ->assertSee('Product images, prices, availability and details are provided by shops or suppliers and may vary.')
            ->assertSee('Category fit and color disclaimer.')
            ->assertSee('Sold By')
            ->assertSee('View Shop')
            ->assertSee('WhatsApp')
            ->assertSee('https://wa.me/919870035848?text=', false)
            ->assertSee('Dynamic%20Cotton%20Shirt', false)
            ->assertSee('Ask A Question')
            ->assertSee('Offers from '.$fixture['shop']->name)
            ->assertSee('Store deals will appear here')
            ->assertDontSee('shop-default.html', false)
            ->assertSee('Related Cotton Shirt')
            ->getContent();

        $breadcrumbs = $this->productBreadcrumbSection($content);
        $this->assertStringContainsString('href="'.route('storefront.home').'"', $breadcrumbs);
        $this->assertStringContainsString('href="'.$this->categoryUrl($fixture['root']).'"', $breadcrumbs);
        $this->assertStringContainsString('href="'.$this->categoryUrl($fixture['category']->parent).'"', $breadcrumbs);
        $this->assertStringContainsString('href="'.$this->categoryUrl($fixture['category']).'"', $breadcrumbs);
        $this->assertStringContainsString('Apparel', $breadcrumbs);
        $this->assertStringContainsString('Women', $breadcrumbs);
        $this->assertStringContainsString('T-Shirts', $breadcrumbs);
        $this->assertStringContainsString('Dynamic Cotton Shirt', $breadcrumbs);
        $this->assertStringNotContainsString('Products', $breadcrumbs);
        $this->assertStringNotContainsString('href="'.$canonicalUrl.'">Dynamic Cotton Shirt</a>', $breadcrumbs);
        $this->assertStringContainsString('<title>Cotton Shirt Detail | LocalHyper</title>', $content);
        $this->assertStringContainsString('<meta name="description" content="Cotton shirt meta description on LocalHyper.">', $content);
        $this->assertStringContainsString('<link rel="canonical" href="'.$canonicalUrl.'">', $content);
        $this->assertStringContainsString('assets/storefront/css/photoswipe.css', $content);
        $this->assertStringContainsString('assets/storefront/css/drift-basic.min.css', $content);
        $this->assertStringContainsString('data-pswp-width="576px"', $content);
        $this->assertStringContainsString('assets/storefront/js/plugin/photoswipe-lightbox.umd.min.js', $content);
        $this->assertStringContainsString('assets/storefront/js/plugin/photoswipe.umd.min.js', $content);
        $this->assertStringNotContainsString('static placeholders', $content);
        $this->assertStringContainsString('product-specification', $content);
        $this->assertStringContainsString('product_d_table', $content);
        $this->assertStringContainsString('Add To Cart', $content);
        $this->assertStringContainsString('sold-by-card', $content);
        $this->assertStringContainsString('Customer Reviews', $content);
        $this->assertStringContainsString('Sample review layout', $content);
        $this->assertStringContainsString('Useful product details before visiting', $content);
        $this->assertStringNotContainsString('(0 reviews)', $content);
        $this->assertStringNotContainsString('Contact Store', $content);
    }

    public function test_old_and_mismatched_product_urls_redirect_to_canonical_category_product_url(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Canonical Redirect Product');
        $this->variant($product);
        $canonicalUrl = $this->productUrl($product);

        $this->get(route('storefront.product.show', $product->slug))
            ->assertMovedPermanently()
            ->assertRedirect($canonicalUrl);

        $this->get(url('/category/wrong/path/products/'.$product->slug))
            ->assertMovedPermanently()
            ->assertRedirect($canonicalUrl);
    }

    public function test_product_detail_shows_default_shop_return_exchange_policy_and_local_delivery_scope(): void
    {
        $fixture = $this->fixture();
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_min_order_amount', 5000, ShopSetting::TYPE_DECIMAL);
        $product = $this->product($fixture, 'Default Policy Product');
        $this->variant($product);

        $this->get($this->productUrl($product))
            ->assertOk()
            ->assertSee('Refund / Exchange')
            ->assertSee('No Refund')
            ->assertSee('Exchange within 7 days')
            ->assertSee('Delivery Coverage')
            ->assertSee('Local Area Only')
            ->assertSee('Minimum delivery order:')
            ->assertSee('5,000.00')
            ->assertDontSee('Use Shop Policy');
    }

    public function test_product_detail_shows_product_override_policy_and_nationwide_delivery_scope(): void
    {
        $fixture = $this->fixture();
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_scope', 'nationwide', ShopSetting::TYPE_STRING);
        $product = $this->product($fixture, 'Override Policy Product');
        $this->variant($product);
        ProductReturnPolicy::query()->create([
            'product_id' => $product->getKey(),
            'refund_allowed' => true,
            'refund_window_days' => 3,
            'exchange_allowed' => false,
            'exchange_window_days' => 0,
        ]);

        $this->get($this->productUrl($product))
            ->assertOk()
            ->assertSee('Refund within 3 days')
            ->assertSee('No Exchange')
            ->assertSee('Ships Across India')
            ->assertDontSee('Exchange within 7 days');
    }

    public function test_product_detail_quantity_input_uses_selected_variant_stock_as_maximum(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Stock Limited Product');
        $this->variant($product, sellingPrice: '949.00', overrides: ['stock_quantity' => 7]);

        $this->get($this->productUrl($product))
            ->assertOk()
            ->assertSee('data-stock-limit="7"', false)
            ->assertSee('max="7"', false);
    }

    public function test_product_detail_does_not_show_in_stock_when_availability_status_is_missing(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Missing Availability Product', overrides: [
            'availability_status_id' => null,
        ]);
        $this->variant($product, sellingPrice: '949.00', overrides: ['stock_quantity' => 7]);

        $this->get($this->productUrl($product))
            ->assertOk()
            ->assertSee('data-product-availability-display>Unavailable', false)
            ->assertSee('Out of Stock')
            ->assertDontSee('data-product-availability-display>In Stock', false);
    }

    public function test_product_detail_backorder_has_no_stock_max_and_exposes_confirmation_message_data(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Backorder Product', overrides: [
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_BACKORDER)->getKey(),
        ]);
        $this->variant($product, sellingPrice: '949.00', overrides: ['stock_quantity' => 5]);

        $content = $this->get($this->productUrl($product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-availability-code="BACKORDER"', $content);
        $this->assertStringContainsString('data-availability-label="In Stock"', $content);
        $this->assertStringContainsString('data-stock-quantity="5"', $content);
        $this->assertStringContainsString('data-product-availability-display>In Stock', $content);
        $this->assertStringNotContainsString('data-stock-limit="5"', $content);
        $this->assertStringNotContainsString('max="5"', $content);
        $this->assertStringContainsString("quantityValue() <= stock", $content);
    }

    public function test_product_detail_backorder_with_zero_stock_initially_displays_backorder(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Zero Stock Backorder Product', overrides: [
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_BACKORDER)->getKey(),
        ]);
        $this->variant($product, sellingPrice: '949.00', overrides: ['stock_quantity' => 0]);

        $content = $this->get($this->productUrl($product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-availability-code="BACKORDER"', $content);
        $this->assertStringContainsString('data-product-availability-display>Backorder', $content);
        $this->assertStringContainsString('This item is currently not in stock. Your order requires confirmation from the merchant.', $content);
    }

    public function test_product_detail_preorder_display_is_not_transformed_when_stock_exists(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Preorder Display Product', overrides: [
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_PREORDER)->getKey(),
        ]);
        $this->variant($product, sellingPrice: '949.00', overrides: ['stock_quantity' => 5]);

        $this->get($this->productUrl($product))
            ->assertOk()
            ->assertSee('data-product-availability-display>Pre-Order', false)
            ->assertDontSee('data-product-availability-display>In Stock', false);
    }

    public function test_product_detail_disables_plus_button_when_selected_variant_stock_is_one(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Single Stock Product');
        $this->variant($product, overrides: ['stock_quantity' => 1]);

        $content = $this->get($this->productUrl($product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-stock-limit="1"', $content);
        $this->assertStringContainsString('btn-increase" type="button"', $content);
        $this->assertStringContainsString('disabled', $content);
    }

    public function test_product_detail_variant_options_include_variant_specific_stock_limits(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, 'Variant Stock Product');
        $size = $this->attributeGroup('Size', ['Small', 'Large']);
        $small = $this->variant($product, sellingPrice: '949.00', overrides: [
            'name' => 'Small',
            'stock_quantity' => 7,
        ]);
        $large = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => 'Large',
            'mrp' => '100.00',
            'selling_price' => '949.00',
            'stock_quantity' => 3,
            'low_stock_threshold' => 0,
            'is_sellable' => true,
            'is_default' => false,
            'sort_order' => 1,
            'status' => 'active',
        ]);
        $small->attributes()->create([
            'product_attribute_group_id' => $size->getKey(),
            'product_attribute_group_value_id' => $size->values->firstWhere('name', 'Small')->getKey(),
        ]);
        $large->attributes()->create([
            'product_attribute_group_id' => $size->getKey(),
            'product_attribute_group_value_id' => $size->values->firstWhere('name', 'Large')->getKey(),
        ]);

        $content = $this->get($this->productUrl($product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-variant-id="'.$small->getKey().'"', $content);
        $this->assertStringContainsString('data-stock-limit="7"', $content);
        $this->assertStringContainsString('data-variant-id="'.$large->getKey().'"', $content);
        $this->assertStringContainsString('data-stock-limit="3"', $content);
    }

    public function test_nested_category_path_page_resolves_and_redirects_mismatched_paths(): void
    {
        $fixture = $this->fixture();
        $grandchild = ProductCategory::query()->create([
            'parent_id' => $fixture['category']->getKey(),
            'name' => 'Nested Shirts '.Str::random(4),
            'slug' => 'nested-shirts-'.Str::random(8),
            'status' => 'active',
        ]);
        $product = $this->product([...$fixture, 'category' => $grandchild], 'Nested Category Product');
        $this->variant($product);

        $this->get($this->categoryUrl($grandchild))
            ->assertOk()
            ->assertSee('Nested Category Product')
            ->assertSeeInOrder([$fixture['root']->name, $fixture['category']->name, $grandchild->name]);

        $this->get(url('/category/'.$fixture['root']->slug.'/wrong/'.$grandchild->slug))
            ->assertMovedPermanently()
            ->assertRedirect($this->categoryUrl($grandchild));
    }

    public function test_category_urls_use_full_hierarchy_and_short_urls_redirect(): void
    {
        $fixture = $this->fixture();
        $fixture['root']->forceFill([
            'name' => 'Apparel',
            'slug' => 'apparel-1',
        ])->save();
        $fixture['category']->forceFill([
            'name' => 'Women',
            'slug' => 'women-18',
        ])->save();
        $shirts = ProductCategory::query()->create([
            'parent_id' => $fixture['category']->getKey(),
            'name' => 'Shirts',
            'slug' => 'shirts-21',
            'status' => 'active',
        ]);
        $product = $this->product([...$fixture, 'category' => $shirts], 'Hierarchy Product');
        $this->variant($product);

        $this->assertSame(url('/category/apparel-1'), $this->categoryUrl($fixture['root']));
        $this->assertSame(url('/category/apparel-1/women-18'), $this->categoryUrl($fixture['category']));
        $this->assertSame(url('/category/apparel-1/women-18/shirts-21'), $this->categoryUrl($shirts));
        $this->assertSame(url('/category/apparel-1/women-18/shirts-21/products/'.$product->slug), $this->productUrl($product));

        $content = $this->get($this->categoryUrl($shirts))
            ->assertOk()
            ->assertSeeInOrder(['Home', 'Apparel', 'Women', 'Shirts'])
            ->assertSee($this->categoryUrl($fixture['root']), false)
            ->assertSee($this->categoryUrl($fixture['category']), false)
            ->assertSee($this->productUrl($product), false)
            ->getContent();

        $breadcrumbs = $this->productBreadcrumbSection($content);
        $this->assertStringNotContainsString(route('storefront.category.child.show', [$fixture['category']->slug, $shirts->slug]), $breadcrumbs);

        $this->get(url('/category/women-18/shirts-21'))
            ->assertMovedPermanently()
            ->assertRedirect($this->categoryUrl($shirts));

        $this->get(url('/category/apparel-1/not-women/shirts-21'))
            ->assertMovedPermanently()
            ->assertRedirect($this->categoryUrl($shirts));
    }


    public function test_product_detail_rejects_inactive_or_unavailable_products(): void
    {
        $fixture = $this->fixture();
        $inactive = $this->product($fixture, 'Inactive Detail Product', status: 'inactive');
        $this->variant($inactive);

        $this->get(route('storefront.product.show', $inactive->slug))
            ->assertNotFound();

        $noVariant = $this->product($fixture, 'No Variant Detail Product');

        $this->get(route('storefront.product.show', $noVariant->slug))
            ->assertNotFound();
    }

    public function test_category_page_renders_products_for_selected_category_tree_only(): void
    {
        $fixture = $this->fixture();
        $grandchild = ProductCategory::query()->create([
            'parent_id' => $fixture['category']->getKey(),
            'name' => 'Inner Category '.Str::random(4),
            'slug' => 'inner-category-'.Str::random(8),
            'status' => 'active',
        ]);
        $visible = $this->product($fixture, 'Category Tree Product');
        $this->variant($visible, mrp: '1000.00', sellingPrice: '800.00');
        $grandchildProduct = $this->product([...$fixture, 'category' => $grandchild], 'Inner Category Product');
        $this->variant($grandchildProduct);

        $sibling = ProductCategory::query()->create([
            'parent_id' => $fixture['root']->getKey(),
            'name' => 'Sibling Leaf '.Str::random(4),
            'slug' => 'sibling-leaf-'.Str::random(8),
            'status' => 'active',
        ]);
        $siblingProduct = $this->product([...$fixture, 'category' => $sibling], 'Sibling Product');
        $this->variant($siblingProduct);

        $inactive = $this->product($fixture, 'Inactive Category Product', status: 'inactive');
        $this->variant($inactive);

        $this->get(route('storefront.category.show', $fixture['root']->slug))
            ->assertOk()
            ->assertSee($fixture['root']->name)
            ->assertSee($this->categoryUrl($fixture['category']), false)
            ->assertSee($fixture['category']->name)
            ->assertSee('Category Tree Product')
            ->assertSee('Inner Category Product')
            ->assertSee('INR 800.00')
            ->assertSee('INR 1,000.00')
            ->assertSee('-20%')
            ->assertSee('Sibling Product')
            ->assertDontSee('Inactive Category Product');

        $this->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSee($grandchild->name)
            ->assertSee($this->categoryUrl($grandchild), false)
            ->assertDontSee('meta-filter-shop')
            ->assertDontSee('id="listLayout"', false)
            ->assertSee('tf-col-2 md-col-3 lg-col-4', false)
            ->assertSeeInOrder(['Sort', 'Popularity', 'New Arrivals', 'Top Sellers', 'Price High to Low', 'Price Low to High', 'Discount High to Low', 'Rating High To Low'])
            ->assertSeeInOrder(['Home', $fixture['root']->name, $fixture['category']->name])
            ->assertSee('Showing 1-2 out of 2 products')
            ->assertSee('Welcome to '.$fixture['category']->name.' - Discover amazing products and deals!')
            ->assertSee('Filters - '.$fixture['root']->name)
            ->assertSee('Category')
            ->assertSee('Expand all')
            ->assertSee('Collapse all')
            ->assertSee('storefront-filter-collapse', false)
            ->assertSee('Category Tree Product')
            ->assertSee('Inner Category Product')
            ->assertDontSee('Availability')
            ->assertDontSee('Out of Stock')
            ->assertDontSee('Sibling Product');

        $this->get(route('storefront.category.child.show', [$sibling->slug, $fixture['category']->slug]))
            ->assertNotFound();
    }

    public function test_category_page_renders_category_seo_meta_without_keywords(): void
    {
        $fixture = $this->fixture();
        DB::table('system_settings')->insert([
            'key' => 'marketplace_name',
            'label' => 'Marketplace Name',
            'value' => 'LocalHyper',
            'value_type' => 'string',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fixture['category']->forceFill([
            'meta_title' => "Women's T-Shirts Online",
            'meta_description' => "Deals & styles from nearby shops.",
        ])->save();

        $content = $this->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<title>Women&#039;s T-Shirts Online | LocalHyper</title>', $content);
        $this->assertStringContainsString('<meta name="description" content="Deals &amp; styles from nearby shops on LocalHyper.">', $content);
        $this->assertStringNotContainsString('name="keywords"', $content);
    }

    public function test_category_page_renders_mapped_attribute_filters_and_filters_products(): void
    {
        $fixture = $this->fixture();
        $size = $this->attributeGroup('Size', ['Small', 'Medium']);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $fixture['root']->getKey(),
            'product_attribute_group_id' => $size->getKey(),
            'sort_order' => 1,
        ]);

        $smallProduct = $this->product($fixture, 'Small Attribute Product');
        $this->variant($smallProduct);
        $smallProduct->attributes()->create([
            'product_attribute_group_id' => $size->getKey(),
            'product_attribute_group_value_id' => $size->values->firstWhere('name', 'Small')->getKey(),
        ]);

        $mediumProduct = $this->product($fixture, 'Medium Variant Product');
        $mediumVariant = $this->variant($mediumProduct);
        $mediumVariant->attributes()->create([
            'product_attribute_group_id' => $size->getKey(),
            'product_attribute_group_value_id' => $size->values->firstWhere('name', 'Medium')->getKey(),
        ]);

        $response = $this->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSee('Size')
            ->assertSee('Small')
            ->assertSee('Medium')
            ->assertSee('name="attributes['.$size->getKey().'][]"', false)
            ->assertSee('Small Attribute Product')
            ->assertSee('Medium Variant Product');

        $this->assertStringContainsString('Apply Filters', $response->getContent());

        $this->get(route('storefront.category.child.show', [
            $fixture['root']->slug,
            $fixture['category']->slug,
            'attributes' => [$size->getKey() => [$size->values->firstWhere('name', 'Medium')->getKey()]],
        ]))
            ->assertOk()
            ->assertSee('checked', false)
            ->assertSee('Medium Variant Product')
            ->assertDontSee('Small Attribute Product');
    }

    public function test_category_page_filters_products_by_price_and_discount(): void
    {
        $fixture = $this->fixture();
        $deepDiscount = $this->product($fixture, 'Deep Discount Product');
        $this->variant($deepDiscount, mrp: '1000.00', sellingPrice: '400.00');
        $premium = $this->product($fixture, 'Premium Price Product');
        $this->variant($premium, mrp: '2000.00', sellingPrice: '1500.00');

        $this->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSee('Price')
            ->assertSee('Discount')
            ->assertSee('30% or more')
            ->assertSee('70% or more')
            ->assertSee('Deep Discount Product')
            ->assertSee('Premium Price Product');

        $this->get(route('storefront.category.child.show', [
            $fixture['root']->slug,
            $fixture['category']->slug,
            'price_min' => '1000',
            'price_max' => '2000',
        ]))
            ->assertOk()
            ->assertSee('Premium Price Product')
            ->assertDontSee('Deep Discount Product');

        $this->get(route('storefront.category.child.show', [
            $fixture['root']->slug,
            $fixture['category']->slug,
            'discount_min' => ['50'],
        ]))
            ->assertOk()
            ->assertSee('Deep Discount Product')
            ->assertDontSee('Premium Price Product');
    }

    public function test_category_page_orders_same_pin_then_nearby_then_farther_without_filtering(): void
    {
        $this->postalCode('422009', '19.9975000', '73.7898000');
        $this->postalCode('422010', '20.0100000', '73.8000000');
        $this->postalCode('422402', '19.7167000', '73.6333000');

        $fixture = $this->fixture(shopPincode: '422402');
        $sameShop = $this->shop($fixture['merchant'], $fixture['root'], 'Same Pin Shop', pincode: '422009');
        $nearShop = $this->shop($fixture['merchant'], $fixture['root'], 'Near Pin Shop', pincode: '422010');
        $farShop = $this->shop($fixture['merchant'], $fixture['root'], 'Far Pin Shop', pincode: '422402');
        $unknownShop = $this->shop($fixture['merchant'], $fixture['root'], 'Unknown Pin Shop', pincode: '499999');

        $products = [
            $this->product([...$fixture, 'shop' => $sameShop], 'Location Same Pin Product', overrides: ['created_at' => now()->subDays(4)]),
            $this->product([...$fixture, 'shop' => $nearShop], 'Location Near Pin Product', overrides: ['created_at' => now()->subDays(3)]),
            $this->product([...$fixture, 'shop' => $farShop], 'Location Far Pin Product', overrides: ['created_at' => now()->subDays(2)]),
            $this->product([...$fixture, 'shop' => $unknownShop], 'Location Unknown Pin Product', overrides: ['created_at' => now()]),
        ];

        foreach ($products as $product) {
            $this->variant($product);
        }

        $this->withSession([CustomerLocationService::SESSION_KEY => '422009'])
            ->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSeeInOrder([
                'Location Same Pin Product',
                'Location Near Pin Product',
                'Location Far Pin Product',
                'Location Unknown Pin Product',
            ]);
    }

    public function test_category_page_without_selected_pin_keeps_existing_ordering(): void
    {
        $this->postalCode('422009', '19.9975000', '73.7898000');
        $this->postalCode('422402', '19.7167000', '73.6333000');

        $fixture = $this->fixture(shopPincode: '422009');
        $nearShop = $this->shop($fixture['merchant'], $fixture['root'], 'No Pin Near Shop', pincode: '422009');
        $farShop = $this->shop($fixture['merchant'], $fixture['root'], 'No Pin Far Shop', pincode: '422402');
        $nearProduct = $this->product([...$fixture, 'shop' => $nearShop], 'No Pin Later Near Product', overrides: ['sort_order' => 2]);
        $farProduct = $this->product([...$fixture, 'shop' => $farShop], 'No Pin Earlier Far Product', overrides: ['sort_order' => 1]);

        $this->variant($nearProduct);
        $this->variant($farProduct);

        $this->flushSession();

        $this->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSeeInOrder([
                'No Pin Earlier Far Product',
                'No Pin Later Near Product',
            ]);
    }

    public function test_changing_selected_pin_changes_category_product_ordering(): void
    {
        $this->postalCode('422009', '19.9975000', '73.7898000');
        $this->postalCode('422402', '19.7167000', '73.6333000');

        $fixture = $this->fixture(shopPincode: '422009');
        $nashikShop = $this->shop($fixture['merchant'], $fixture['root'], 'Nashik Shop', pincode: '422009');
        $ghotiShop = $this->shop($fixture['merchant'], $fixture['root'], 'Ghoti Shop', pincode: '422402');
        $nashikProduct = $this->product([...$fixture, 'shop' => $nashikShop], 'Change Pin Nashik Product');
        $ghotiProduct = $this->product([...$fixture, 'shop' => $ghotiShop], 'Change Pin Ghoti Product');

        $this->variant($nashikProduct);
        $this->variant($ghotiProduct);

        $this->withSession([CustomerLocationService::SESSION_KEY => '422009'])
            ->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSeeInOrder([
                'Change Pin Nashik Product',
                'Change Pin Ghoti Product',
            ]);

        $this->flushSession();

        $this->withSession([CustomerLocationService::SESSION_KEY => '422402'])
            ->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSeeInOrder([
                'Change Pin Ghoti Product',
                'Change Pin Nashik Product',
            ]);
    }

    public function test_category_pagination_happens_after_location_ordering(): void
    {
        $this->postalCode('422009', '19.9975000', '73.7898000');
        $this->postalCode('422402', '19.7167000', '73.6333000');

        $fixture = $this->fixture(shopPincode: '422402');
        $nearShop = $this->shop($fixture['merchant'], $fixture['root'], 'Paged Near Shop', pincode: '422009');
        $farShop = $this->shop($fixture['merchant'], $fixture['root'], 'Paged Far Shop', pincode: '422402');
        $nearProduct = $this->product([...$fixture, 'shop' => $nearShop], 'Paged Location Near Product', overrides: ['created_at' => now()->subDays(10)]);
        $this->variant($nearProduct);

        foreach (range(1, 12) as $index) {
            $farProduct = $this->product([...$fixture, 'shop' => $farShop], 'Paged Location Far Product '.$index, overrides: ['created_at' => now()->subMinutes($index)]);
            $this->variant($farProduct);
        }

        $this->withSession([CustomerLocationService::SESSION_KEY => '422009'])
            ->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]))
            ->assertOk()
            ->assertSee('Paged Location Near Product')
            ->assertDontSee('Paged Location Far Product 12');

        $this->withSession([CustomerLocationService::SESSION_KEY => '422009'])
            ->get(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug, 'page' => 2]))
            ->assertOk()
            ->assertDontSee('Paged Location Near Product')
            ->assertSee('Paged Location Far Product 12');
    }

    /**
     * @return array{merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory}
     */
    private function fixture(?string $shopPincode = null): array
    {
        $merchant = $this->merchant('Listing Merchant');
        $root = ProductCategory::query()->create([
            'name' => 'Listing Root '.Str::random(4),
            'slug' => 'listing-root-'.Str::random(8),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Listing Leaf '.Str::random(4),
            'slug' => 'listing-leaf-'.Str::random(8),
            'status' => 'active',
        ]);
        $shop = $this->shop($merchant, $root, pincode: $shopPincode);

        return compact('merchant', 'shop', 'root', 'category');
    }

    private function merchant(string $name, string $status = 'active'): MerchantProfile
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::random(6).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $name,
            'verification_status' => 'approved',
            'status' => $status,
        ]);

        app(MerchantAvailabilityStatusSeeder::class)->seedDefaultsForMerchant($merchant);

        return $merchant;
    }

    private function shop(MerchantProfile $merchant, ProductCategory $root, string $name = 'Listing Shop', string $status = 'active', ?string $pincode = null): Shop
    {
        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'pincode' => $pincode,
            'status' => $status,
        ]);
    }

    /**
     * @param array{merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory} $fixture
     * @param array<string, mixed> $overrides
     */
    private function product(array $fixture, string $name, string $status = 'active', array $overrides = []): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['root']->getKey(),
            'product_category_id' => $fixture['category']->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'availability_status_id' => ProductAvailabilityStatus::query()
                ->where('merchant_id', $fixture['merchant']->getKey())
                ->where('code', ProductAvailabilityStatus::CODE_IN_STOCK)
                ->value('id'),
            'status' => $status,
            'tax_mode' => 'inherit',
            ...$overrides,
        ]);
    }

    private function variant(Product $product, string $mrp = '100.00', string $sellingPrice = '90.00', array $overrides = []): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => $product->product_name,
            'mrp' => $mrp,
            'selling_price' => $sellingPrice,
            'stock_quantity' => 5,
            'low_stock_threshold' => 0,
            'is_sellable' => true,
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
            ...$overrides,
        ]);
    }

    private function image(Product $product, string $path): ProductImage
    {
        Storage::disk('public')->put($path, 'image');

        $image = ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'image_path' => str_replace('/thumb.', '/original.', $path),
            'thumbnail_path' => $path,
            'is_primary' => true,
            'status' => 'active',
        ]);
        $product->forceFill(['primary_image_id' => $image->getKey()])->save();

        return $image;
    }

    private function availabilityStatus(MerchantProfile $merchant, string $code): ProductAvailabilityStatus
    {
        return ProductAvailabilityStatus::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * @param array<int, string> $values
     */
    private function attributeGroup(string $name, array $values): ProductAttributeGroup
    {
        $group = ProductAttributeGroup::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'code' => Str::slug($name),
            'selection_type' => 'multiple',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        foreach ($values as $index => $value) {
            ProductAttributeGroupValue::query()->create([
                'uuid' => (string) Str::uuid(),
                'product_attribute_group_id' => $group->getKey(),
                'name' => $value,
                'code' => Str::slug($value),
                'status' => 'active',
                'sort_order' => $index,
            ]);
        }

        return $group->load('values');
    }

    private function currencySetting(string $key, string $value, string $type = AdminSetting::TYPE_STRING): void
    {
        AdminSetting::query()->updateOrCreate(
            ['group' => 'currency', 'setting_key' => $key],
            ['setting_value' => $value, 'setting_type' => $type],
        );
    }

    private function shopSetting(Shop $shop, string $group, string $key, mixed $value, string $type): void
    {
        app(ShopSettingsService::class)->setTyped($shop->getKey(), $group, $key, $value, $type);
    }

    private function postalCode(string $postalCode, string $latitude, string $longitude): PostalCode
    {
        return PostalCode::query()->create([
            'source_key' => sha1($postalCode.'|listing test h.o|ho|nashik|maharashtra'),
            'circle_name' => 'Maharashtra Circle',
            'region_name' => 'Mumbai Region',
            'division_name' => 'Nashik Division',
            'office_name' => 'Listing Test H.O',
            'postal_code' => $postalCode,
            'office_type' => 'HO',
            'delivery_status' => 'Delivery',
            'shipping_enabled' => true,
            'district' => 'NASHIK',
            'state' => 'MAHARASHTRA',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => PostalCode::STATUS_ACTIVE,
        ]);
    }

    private function productCardSection(string $content, string $productName): string
    {
        $namePosition = strpos($content, $productName);
        $this->assertIsInt($namePosition);

        $start = strrpos(substr($content, 0, $namePosition), '<div class="card-product ');
        $this->assertIsInt($start);

        $end = strpos($content, '<div class="card-product ', $namePosition);

        return substr($content, $start, $end === false ? null : $end - $start);
    }

    private function productsListingSection(string $content): string
    {
        $start = strpos($content, '<div class="wrapper-control-shop gridLayout-wrapper">');
        $this->assertIsInt($start);

        $end = strpos($content, '<div class="offcanvas offcanvas-start canvas-filter"', $start);
        $this->assertIsInt($end);

        return substr($content, $start, $end - $start);
    }

    private function productBreadcrumbSection(string $content): string
    {
        $start = strpos($content, '<div class="category-listing-breadcrumbs');
        $this->assertIsInt($start);

        $end = strpos($content, '</div>', $start);
        $this->assertIsInt($end);

        return substr($content, $start, $end - $start);
    }

    private function productUrl(Product $product): string
    {
        return app(StorefrontUrlService::class)->product($product);
    }

    private function categoryUrl(ProductCategory $category): string
    {
        return app(StorefrontUrlService::class)->category($category);
    }
}
