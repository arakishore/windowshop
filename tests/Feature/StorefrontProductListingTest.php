<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
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
            $product = $this->product($fixture, 'Paged Product '.$index, overrides: ['created_at' => now()->subMinutes($index)]);
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
            ->assertSee(route('storefront.category.child.show', [$fixture['root']->slug, $fixture['category']->slug]), false)
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
            ->assertSee(route('storefront.category.child.show', [$fixture['category']->slug, $grandchild->slug]), false)
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

    /**
     * @return array{merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory}
     */
    private function fixture(): array
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
        $shop = $this->shop($merchant, $root);

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

        return MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $name,
            'verification_status' => 'approved',
            'status' => $status,
        ]);
    }

    private function shop(MerchantProfile $merchant, ProductCategory $root, string $name = 'Listing Shop', string $status = 'active'): Shop
    {
        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
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
            'status' => $status,
            'tax_mode' => 'inherit',
            ...$overrides,
        ]);
    }

    private function variant(Product $product, string $mrp = '100.00', string $sellingPrice = '90.00'): ProductVariant
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
}
