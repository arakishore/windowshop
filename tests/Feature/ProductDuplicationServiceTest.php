<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Shop;
use App\Models\User;
use App\Services\Product\ProductDuplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ProductDuplicationServiceTest extends TestCase
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

    public function test_it_duplicates_product_data_variants_attributes_and_physical_images_as_draft(): void
    {
        Storage::fake('public');
        [$user, $shop, $category] = $this->fixture();
        [$color, $red] = $this->colorAttribute();
        $product = Product::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $category->getKey(),
            'product_name' => 'Cotton Shirt',
            'slug' => 'cotton-shirt-original',
            'short_description' => 'Short',
            'description' => 'Long description',
            'meta_title' => 'SEO title',
            'meta_description' => 'SEO description',
            'status' => 'active',
            'published_at' => now(),
        ]);
        ProductAttribute::query()->create([
            'product_id' => $product->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $red->getKey(),
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $shop->getKey(),
            'sku' => 'SKU-ORIGINAL',
            'barcode' => 'BAR-ORIGINAL',
            'name' => 'Red',
            'mrp' => 1200,
            'selling_price' => 999,
            'cost_price' => 600,
            'stock_quantity' => 17,
            'low_stock_threshold' => 4,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);
        ProductVariantAttribute::query()->create([
            'product_variant_id' => $variant->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $red->getKey(),
        ]);
        $image = ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'image_path' => '',
            'thumbnail_path' => null,
            'is_primary' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);
        $directory = "products/{$product->getKey()}-{$product->uuid}/images";
        $image->forceFill([
            'image_path' => "{$directory}/p{$product->getKey()}-img{$image->getKey()}-web.webp",
            'thumbnail_path' => "{$directory}/p{$product->getKey()}-img{$image->getKey()}-thumb.webp",
        ])->save();
        $image->attributeValues()->sync([$red->getKey()]);
        $product->forceFill(['primary_image_id' => $image->getKey()])->save();

        Storage::disk('public')->put($image->image_path, 'web image');
        Storage::disk('public')->put($image->thumbnail_path, 'thumb image');
        Storage::disk('public')->put("{$directory}/p{$product->getKey()}-img{$image->getKey()}-app.webp", 'app image');

        $duplicate = app(ProductDuplicationService::class)->duplicate($product, $user);
        $duplicate->load('attributes', 'variants.attributes', 'images.attributeValues', 'primaryImage');

        $this->assertNotSame($product->getKey(), $duplicate->getKey());
        $this->assertSame('Cotton Shirt - Copy', $duplicate->product_name);
        $this->assertSame('draft', $duplicate->status);
        $this->assertNull($duplicate->published_at);
        $this->assertSame($product->product_category_id, $duplicate->product_category_id);
        $this->assertSame('Short', $duplicate->short_description);
        $this->assertSame('Long description', $duplicate->description);
        $this->assertSame('SEO title', $duplicate->meta_title);
        $this->assertSame('SEO description', $duplicate->meta_description);
        $this->assertSame(1, $duplicate->attributes->count());

        $copiedVariant = $duplicate->variants->first();
        $this->assertNotNull($copiedVariant);
        $this->assertNull($copiedVariant->sku);
        $this->assertNull($copiedVariant->barcode);
        $this->assertSame(0, $copiedVariant->stock_quantity);
        $this->assertSame(4, $copiedVariant->low_stock_threshold);
        $this->assertTrue((bool) $copiedVariant->is_default);
        $this->assertSame(1, $copiedVariant->attributes->count());

        $copiedImage = $duplicate->images->first();
        $this->assertNotNull($copiedImage);
        $this->assertSame($copiedImage->getKey(), $duplicate->primary_image_id);
        $this->assertTrue((bool) $copiedImage->is_primary);
        $this->assertSame([$red->getKey()], $copiedImage->attributeValues->pluck('id')->all());
        $this->assertStringStartsWith("products/{$duplicate->getKey()}-{$duplicate->uuid}/images/", $copiedImage->image_path);
        $this->assertSame("p{$duplicate->getKey()}-img{$copiedImage->getKey()}-web.webp", basename($copiedImage->image_path));

        Storage::disk('public')->assertExists($image->image_path);
        Storage::disk('public')->assertExists($copiedImage->image_path);
        Storage::disk('public')->assertExists($copiedImage->thumbnail_path);
        Storage::disk('public')->assertExists(dirname($copiedImage->image_path)."/p{$duplicate->getKey()}-img{$copiedImage->getKey()}-app.webp");
    }

    /**
     * @return array{0: User, 1: Shop, 2: ProductCategory}
     */
    private function fixture(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => 'duplicate-'.Str::random(6).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Duplicate Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts',
            'slug' => 'shirts-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Duplicate Shop',
            'slug' => 'duplicate-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return [$user, $shop, $category];
    }

    /**
     * @return array{0: ProductAttributeGroup, 1: ProductAttributeGroupValue}
     */
    private function colorAttribute(): array
    {
        $color = ProductAttributeGroup::query()->create([
            'name' => 'Color',
            'code' => 'color-'.Str::random(4),
            'selection_type' => 'multiple',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $red = ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $color->getKey(),
            'name' => 'Red',
            'code' => 'red-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 1,
        ]);

        return [$color, $red];
    }
}
