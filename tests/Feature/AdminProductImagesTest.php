<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Shop;
use App\Models\User;
use App\Services\Product\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminProductImagesTest extends TestCase
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

    public function test_upload_assigns_first_image_as_primary(): void
    {
        Storage::fake('public');
        [$admin, $product] = $this->productFixture();

        $this->actingAs($admin)
            ->post(route('admin.products.images.store', $product), [
                'images' => [
                    UploadedFile::fake()->image('front.jpg', 900, 900),
                    UploadedFile::fake()->image('side.png', 900, 900),
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']));

        $product->refresh();

        $this->assertSame(2, $product->images()->count());
        $this->assertNotNull($product->primary_image_id);
        $this->assertTrue((bool) $product->primaryImage()->first()?->is_primary);

        $firstImage = $product->images()->orderBy('id')->firstOrFail();
        $this->assertStringStartsWith(
            "products/{$product->getKey()}-{$product->uuid}/images/",
            $firstImage->image_path,
        );
        $this->assertSame(
            "p{$product->getKey()}-img{$firstImage->getKey()}-web.webp",
            basename($firstImage->image_path),
        );
    }

    public function test_admin_can_change_primary_and_delete_falls_back_to_next_active_image(): void
    {
        [$admin, $product] = $this->productFixture();
        $first = $this->createImage($product, 1);
        $second = $this->createImage($product, 2);

        $product->forceFill(['primary_image_id' => $first->getKey()])->save();
        $first->forceFill(['is_primary' => true])->save();

        $this->actingAs($admin)
            ->put(route('admin.products.images.update', $product), [
                'primary_image_id' => $second->getKey(),
                'images' => [
                    $first->getKey() => ['title' => 'First', 'alt_text' => 'First alt', 'sort_order' => '1', 'status' => 'active'],
                    $second->getKey() => ['title' => 'Second', 'alt_text' => 'Second alt', 'sort_order' => '2', 'status' => 'active'],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']));

        $this->assertSame($second->getKey(), $product->fresh()->primary_image_id);

        $this->actingAs($admin)
            ->put(route('admin.products.images.update', $product), [
                'primary_image_id' => $second->getKey(),
                'images' => [
                    $first->getKey() => ['title' => 'First', 'alt_text' => 'First alt', 'sort_order' => '1', 'status' => 'active'],
                    $second->getKey() => ['title' => 'Second', 'alt_text' => 'Second alt', 'sort_order' => '2', 'status' => 'inactive'],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']));

        $this->assertSame($first->getKey(), $product->fresh()->primary_image_id);

        $second->refresh()->forceFill(['status' => 'active'])->save();
        $product->forceFill(['primary_image_id' => $second->getKey()])->save();

        $this->actingAs($admin)
            ->delete(route('admin.products.images.destroy', ['product' => $product, 'productImage' => $second]))
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']));

        $this->assertSame($first->getKey(), $product->fresh()->primary_image_id);
        $this->assertDatabaseMissing('product_images', [
            'id' => $second->getKey(),
        ]);
    }

    public function test_admin_can_bulk_hard_delete_images_and_physical_files(): void
    {
        Storage::fake('public');
        [$admin, $product] = $this->productFixture();
        $first = $this->createImage($product, 1);
        $second = $this->createImage($product, 2);
        $third = $this->createImage($product, 3);

        Storage::disk('public')->put($first->image_path, 'first');
        Storage::disk('public')->put($first->thumbnail_path, 'first thumb');
        Storage::disk('public')->put($second->image_path, 'second');
        Storage::disk('public')->put($second->thumbnail_path, 'second thumb');
        Storage::disk('public')->put(dirname($second->image_path)."/p{$product->getKey()}-img{$second->getKey()}-card.webp", 'second card');
        Storage::disk('public')->put(dirname($second->image_path)."/p{$product->getKey()}-img{$second->getKey()}-app.webp", 'second app');
        Storage::disk('public')->put($third->image_path, 'third');
        Storage::disk('public')->put($third->thumbnail_path, 'third thumb');

        $product->forceFill(['primary_image_id' => $second->getKey()])->save();
        $second->forceFill(['is_primary' => true])->save();

        $this->actingAs($admin)
            ->delete(route('admin.products.images.bulk-destroy', $product), [
                'image_ids' => [$second->getKey(), $third->getKey()],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']));

        $this->assertDatabaseHas('product_images', [
            'id' => $first->getKey(),
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('product_images', [
            'id' => $second->getKey(),
        ]);
        $this->assertDatabaseMissing('product_images', [
            'id' => $third->getKey(),
        ]);
        Storage::disk('public')->assertExists($first->image_path);
        Storage::disk('public')->assertMissing($second->image_path);
        Storage::disk('public')->assertMissing($second->thumbnail_path);
        Storage::disk('public')->assertMissing(dirname($second->image_path)."/p{$product->getKey()}-img{$second->getKey()}-card.webp");
        Storage::disk('public')->assertMissing(dirname($second->image_path)."/p{$product->getKey()}-img{$second->getKey()}-app.webp");
        Storage::disk('public')->assertMissing($third->image_path);
        $this->assertSame($first->getKey(), $product->fresh()->primary_image_id);
    }

    public function test_image_attribute_mapping_uses_selected_product_values_only(): void
    {
        Storage::fake('public');
        [$admin, $product, $color, $red, $blue] = $this->productFixtureWithImageAttribute();
        $green = ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $color->getKey(),
            'name' => 'Green',
            'code' => 'green-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 3,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.images.store', $product), [
                'attribute_value_id' => $red->getKey(),
                'images' => [UploadedFile::fake()->image('red.webp', 900, 900)],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']));

        $image = $product->images()->firstOrFail();

        $this->assertDatabaseHas('product_image_attribute_values', [
            'product_image_id' => $image->getKey(),
            'product_attribute_group_value_id' => $red->getKey(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'images']))
            ->post(route('admin.products.images.store', $product), [
                'attribute_value_id' => $green->getKey(),
                'images' => [UploadedFile::fake()->image('green.jpg', 900, 900)],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']))
            ->assertSessionHasErrors('attribute_value_id');

        $this->assertSame(1, $product->images()->count());
        $this->assertTrue($blue->exists);
    }

    public function test_admin_can_upload_entire_product_and_color_images_together(): void
    {
        Storage::fake('public');
        [$admin, $product, $color, $red, $blue] = $this->productFixtureWithImageAttribute();

        $this->actingAs($admin)
            ->post(route('admin.products.images.store', $product), [
                'image_groups' => [
                    'entire' => [UploadedFile::fake()->image('general.jpg', 900, 900)],
                    $red->getKey() => [UploadedFile::fake()->image('red.jpg', 900, 900)],
                    $blue->getKey() => [UploadedFile::fake()->image('blue.jpg', 900, 900)],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']));

        $images = $product->images()->with('attributeValues')->get();

        $this->assertSame(3, $images->count());
        $this->assertCount(1, $images->filter(fn (ProductImage $image): bool => $image->attributeValues->isEmpty()));
        $this->assertDatabaseHas('product_image_attribute_values', [
            'product_attribute_group_value_id' => $red->getKey(),
        ]);
        $this->assertDatabaseHas('product_image_attribute_values', [
            'product_attribute_group_value_id' => $blue->getKey(),
        ]);
        $this->assertNotNull($product->fresh()->primary_image_id);
        $this->assertTrue($color->exists);
    }

    public function test_gallery_prefers_variant_image_attribute_then_general_then_primary(): void
    {
        [$admin, $product, $color, $red, $blue] = $this->productFixtureWithImageAttribute();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => 'Blue / XL',
            'mrp' => 1000,
            'selling_price' => 900,
            'stock_quantity' => 5,
            'is_default' => false,
            'sort_order' => 1,
            'status' => 'active',
        ]);
        ProductVariantAttribute::query()->create([
            'product_variant_id' => $variant->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $blue->getKey(),
        ]);

        $redImage = $this->createImage($product, 1);
        $redImage->attributeValues()->sync([$red->getKey()]);
        $generalImage = $this->createImage($product, 2);
        $primaryImage = $this->createImage($product, 3);
        $product->forceFill(['primary_image_id' => $primaryImage->getKey()])->save();

        $service = app(ProductImageService::class);

        $this->assertSame(
            [$generalImage->getKey(), $primaryImage->getKey()],
            $service->galleryForVariant($product, $variant)->pluck('id')->all(),
        );

        $generalImage->forceFill(['status' => 'inactive'])->save();

        $this->assertSame([$primaryImage->getKey()], $service->galleryForVariant($product, $variant)->pluck('id')->all());

        $blueImage = $this->createImage($product, 4);
        $blueImage->attributeValues()->sync([$blue->getKey()]);

        $this->assertSame([$blueImage->getKey()], $service->galleryForVariant($product, $variant)->pluck('id')->all());
        $this->assertTrue($admin->exists);
    }

    public function test_default_and_selected_color_variants_control_gallery_without_size_affecting_images(): void
    {
        [$admin, $product, $color, $red, $blue] = $this->productFixtureWithImageAttribute();
        $size = ProductAttributeGroup::query()->create([
            'name' => 'Size',
            'code' => 'size-'.Str::random(4),
            'selection_type' => 'multiple',
            'status' => 'active',
            'sort_order' => 2,
        ]);
        $small = ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $size->getKey(),
            'name' => 'S',
            'code' => 's-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $medium = ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $size->getKey(),
            'name' => 'M',
            'code' => 'm-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 2,
        ]);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $product->root_product_category_id,
            'product_attribute_group_id' => $size->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'is_image_attribute' => false,
            'sort_order' => 2,
        ]);

        foreach ([$small, $medium] as $value) {
            ProductAttribute::query()->create([
                'product_id' => $product->getKey(),
                'product_attribute_group_id' => $size->getKey(),
                'product_attribute_group_value_id' => $value->getKey(),
            ]);
        }

        $redSmall = $this->createVariant($product, 'Red / S', true, [
            [$color, $red],
            [$size, $small],
        ]);
        $redMedium = $this->createVariant($product, 'Red / M', false, [
            [$color, $red],
            [$size, $medium],
        ]);
        $blueSmall = $this->createVariant($product, 'Blue / S', false, [
            [$color, $blue],
            [$size, $small],
        ]);

        $redImage = $this->createImage($product, 1);
        $redImage->attributeValues()->sync([$red->getKey()]);
        $blueImage = $this->createImage($product, 2);
        $blueImage->attributeValues()->sync([$blue->getKey()]);
        $generalImage = $this->createImage($product, 3);
        $product->forceFill(['primary_image_id' => $generalImage->getKey()])->save();

        $service = app(ProductImageService::class);

        $this->assertSame([$redImage->getKey()], $service->galleryForVariant($product, $redSmall)->pluck('id')->all());
        $this->assertSame([$blueImage->getKey()], $service->galleryForVariant($product, $blueSmall)->pluck('id')->all());
        $this->assertSame([$redImage->getKey()], $service->galleryForVariant($product, $redMedium)->pluck('id')->all());

        $blueImage->attributeValues()->detach();
        $blueImage->forceFill(['status' => 'inactive'])->save();

        $this->assertSame([$generalImage->getKey()], $service->galleryForVariant($product, $blueSmall)->pluck('id')->all());
        $this->assertTrue($admin->exists);
    }

    public function test_images_cannot_be_mapped_to_non_primary_image_attribute_values(): void
    {
        Storage::fake('public');
        [$admin, $product] = $this->productFixture();
        $size = ProductAttributeGroup::query()->create([
            'name' => 'Size',
            'code' => 'size-'.Str::random(4),
            'selection_type' => 'multiple',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $small = ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $size->getKey(),
            'name' => 'S',
            'code' => 's-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 1,
        ]);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $product->root_product_category_id,
            'product_attribute_group_id' => $size->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'is_image_attribute' => false,
            'sort_order' => 1,
        ]);
        ProductAttribute::query()->create([
            'product_id' => $product->getKey(),
            'product_attribute_group_id' => $size->getKey(),
            'product_attribute_group_value_id' => $small->getKey(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'images']))
            ->post(route('admin.products.images.store', $product), [
                'attribute_value_id' => $small->getKey(),
                'images' => [UploadedFile::fake()->image('size.jpg', 900, 900)],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'images']))
            ->assertSessionHasErrors('attribute_value_id');

        $this->assertSame(0, $product->images()->count());
    }

    private function productFixture(): array
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel');
        $category = $this->createCategory('Shirts', $root);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $admin->getKey(),
            'business_name' => 'Demo Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Demo Shop',
            'slug' => 'demo-shop-'.Str::random(6),
            'address_line_1' => 'Nashik',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Image Product',
            'slug' => 'image-product-'.Str::random(6),
            'status' => 'draft',
        ]);

        return [$admin, $product];
    }

    private function productFixtureWithImageAttribute(): array
    {
        [$admin, $product] = $this->productFixture();
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
        $blue = ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $color->getKey(),
            'name' => 'Blue',
            'code' => 'blue-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 2,
        ]);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $product->root_product_category_id,
            'product_attribute_group_id' => $color->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'is_image_attribute' => true,
            'sort_order' => 1,
        ]);
        ProductAttribute::query()->create([
            'product_id' => $product->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $red->getKey(),
        ]);
        ProductAttribute::query()->create([
            'product_id' => $product->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $blue->getKey(),
        ]);

        return [$admin, $product, $color, $red, $blue];
    }

    private function createImage(Product $product, int $sortOrder): ProductImage
    {
        return ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'image_path' => "products/{$product->getKey()}-{$product->uuid}/images/p{$product->getKey()}-img{$sortOrder}-web.webp",
            'thumbnail_path' => "products/{$product->getKey()}-{$product->uuid}/images/p{$product->getKey()}-img{$sortOrder}-thumb.webp",
            'sort_order' => $sortOrder,
            'status' => 'active',
        ]);
    }

    /**
     * @param array<int, array{0: ProductAttributeGroup, 1: ProductAttributeGroupValue}> $attributes
     */
    private function createVariant(Product $product, string $name, bool $default, array $attributes): ProductVariant
    {
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => $name,
            'mrp' => 1000,
            'selling_price' => 900,
            'stock_quantity' => 5,
            'is_default' => $default,
            'sort_order' => $default ? 1 : 2,
            'status' => 'active',
        ]);

        foreach ($attributes as [$group, $value]) {
            ProductVariantAttribute::query()->create([
                'product_variant_id' => $variant->getKey(),
                'product_attribute_group_id' => $group->getKey(),
                'product_attribute_group_value_id' => $value->getKey(),
            ]);
        }

        return $variant;
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'product-images-admin-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $roleId = DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function createCategory(string $name, ?ProductCategory $parent = null): ProductCategory
    {
        return ProductCategory::query()->create([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
        ]);
    }
}
