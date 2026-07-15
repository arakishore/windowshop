<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ProductArchiveDeleteTest extends TestCase
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

    public function test_merchant_can_archive_restore_archive_and_soft_delete_own_product(): void
    {
        [$merchantUser, $shop, $product] = $this->productFixture();

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.archive', $product))
            ->assertRedirect(route('merchant.products.index'));

        $product->refresh();
        $this->assertSame('archived', $product->status);
        $this->assertNull($product->deleted_at);
        $this->assertNull($product->deleted_by);

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.products.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Lifecycle Product');

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.restore-archive', $product))
            ->assertRedirect(route('merchant.products.index', ['status' => 'archived']));

        $this->assertSame('draft', $product->fresh()->status);

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->delete(route('merchant.products.destroy', $product))
            ->assertRedirect(route('merchant.products.index'));

        $deleted = Product::withTrashed()->findOrFail($product->getKey());
        $this->assertTrue($deleted->trashed());
        $this->assertSame('draft', $deleted->status);
        $this->assertSame($merchantUser->getKey(), $deleted->deleted_by);

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.products.index'))
            ->assertOk()
            ->assertDontSee('Lifecycle Product');
    }

    public function test_admin_can_view_trash_restore_as_draft_and_permanently_delete_with_files(): void
    {
        Storage::fake('public');
        [$merchantUser, $shop, $product] = $this->productFixture();
        $admin = $this->createAdminUser();
        $image = $this->createImage($product);
        Storage::disk('public')->put($image->image_path, 'web');
        Storage::disk('public')->put($image->thumbnail_path, 'thumb');
        Storage::disk('public')->put(dirname($image->image_path)."/p{$product->getKey()}-img{$image->getKey()}-app.webp", 'app');

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $deleted = Product::withTrashed()->findOrFail($product->getKey());
        $this->assertTrue($deleted->trashed());
        $this->assertSame('active', $deleted->status);
        $this->assertSame($admin->getKey(), $deleted->deleted_by);

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['status' => 'trash']))
            ->assertOk()
            ->assertSee('Lifecycle Product')
            ->assertSee('Trash');

        $this->actingAs($admin)
            ->post(route('admin.products.restore-trash', $deleted))
            ->assertRedirect(route('admin.products.index', ['status' => 'trash']));

        $restored = $product->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertSame('draft', $restored->status);
        $this->assertNull($restored->deleted_by);
        Storage::disk('public')->assertExists($image->image_path);

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $restored))
            ->assertRedirect(route('admin.products.index'));

        $trashed = Product::withTrashed()->findOrFail($product->getKey());

        $this->actingAs($admin)
            ->delete(route('admin.products.force-destroy', $trashed))
            ->assertRedirect(route('admin.products.index', ['status' => 'trash']));

        $this->assertDatabaseMissing('products', ['id' => $product->getKey()]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->getKey()]);
        Storage::disk('public')->assertMissing($image->image_path);
        Storage::disk('public')->assertMissing($image->thumbnail_path);
        Storage::disk('public')->assertMissing(dirname($image->image_path)."/p{$product->getKey()}-img{$image->getKey()}-app.webp");
        $this->assertTrue($merchantUser->exists);
        $this->assertTrue($shop->exists);
    }

    public function test_purge_command_permanently_deletes_products_in_trash_for_more_than_45_days(): void
    {
        Storage::fake('public');
        [$merchantUser, $shop, $oldProduct] = $this->productFixture('Old Trash Product');
        [, , $recentProduct] = $this->productFixture('Recent Trash Product', 'recent-merchant@example.test', 'Recent Shop');
        $oldImage = $this->createImage($oldProduct);
        $recentImage = $this->createImage($recentProduct);
        Storage::disk('public')->put($oldImage->image_path, 'old');
        Storage::disk('public')->put($recentImage->image_path, 'recent');

        $oldProduct->delete();
        $oldProduct->forceFill(['deleted_at' => now()->subDays(46), 'deleted_by' => $merchantUser->getKey()])->save();
        $recentProduct->delete();
        $recentProduct->forceFill(['deleted_at' => now()->subDays(10), 'deleted_by' => $merchantUser->getKey()])->save();

        Artisan::call('products:purge-trash');

        $this->assertDatabaseMissing('products', ['id' => $oldProduct->getKey()]);
        $this->assertDatabaseHas('products', ['id' => $recentProduct->getKey()]);
        Storage::disk('public')->assertMissing($oldImage->image_path);
        Storage::disk('public')->assertExists($recentImage->image_path);
        $this->assertTrue($shop->exists);
    }

    public function test_admin_bulk_actions_archive_delete_restore_and_force_delete_products(): void
    {
        Storage::fake('public');
        [, , $first] = $this->productFixture('Admin Bulk One');
        [, , $second] = $this->productFixture('Admin Bulk Two', 'admin-bulk-two@example.test', 'Admin Bulk Two Shop');
        $admin = $this->createAdminUser();
        $image = $this->createImage($second);
        Storage::disk('public')->put($image->image_path, 'web');

        $this->actingAs($admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'archive',
                'product_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame('archived', $first->fresh()->status);
        $this->assertSame('archived', $second->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'restore_archive',
                'product_ids' => [$first->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame('draft', $first->fresh()->status);
        $this->assertSame('archived', $second->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'delete',
                'product_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertRedirect();

        $this->assertTrue(Product::withTrashed()->findOrFail($first->getKey())->trashed());
        $this->assertTrue(Product::withTrashed()->findOrFail($second->getKey())->trashed());

        $this->actingAs($admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'restore_trash',
                'product_ids' => [$first->getKey()],
            ])
            ->assertRedirect();

        $this->assertFalse($first->fresh()->trashed());
        $this->assertSame('draft', $first->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'force_delete',
                'product_ids' => [$second->getKey()],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $second->getKey()]);
        Storage::disk('public')->assertMissing($image->image_path);
    }

    public function test_merchant_bulk_actions_are_limited_to_active_shop_products(): void
    {
        [$merchantUser, $shop, $first] = $this->productFixture('Merchant Bulk One');
        [, , $second] = $this->productFixture('Merchant Bulk Two', 'merchant-bulk-two@example.test', 'Merchant Bulk Two Shop');

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.bulk-action'), [
                'action' => 'archive',
                'product_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame('archived', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.bulk-action'), [
                'action' => 'restore_archive',
                'product_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame('draft', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.bulk-action'), [
                'action' => 'delete',
                'product_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertRedirect();

        $this->assertTrue(Product::withTrashed()->findOrFail($first->getKey())->trashed());
        $this->assertFalse(Product::withTrashed()->findOrFail($second->getKey())->trashed());
    }

    public function test_admin_and_merchant_can_bulk_mark_products_active_and_inactive(): void
    {
        [$merchantUser, $shop, $first] = $this->productFixture('Bulk Status One');
        [, , $second] = $this->productFixture('Bulk Status Two', 'bulk-status-two@example.test', 'Bulk Status Two Shop');
        $admin = $this->createAdminUser();

        foreach ([$first, $second] as $product) {
            $product->forceFill(['status' => 'draft'])->save();
            $this->createPublishableVariant($product);
        }

        $this->actingAs($admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'mark_active',
                'product_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame('active', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);

        $this->actingAs($merchantUser)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.bulk-action'), [
                'action' => 'mark_inactive',
                'product_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame('inactive', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Shop, 2: Product}
     */
    private function productFixture(
        string $productName = 'Lifecycle Product',
        string $email = 'merchant-lifecycle@example.test',
        string $shopName = 'Lifecycle Shop',
    ): array {
        $merchantUser = $this->createMerchantUser($email);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => 'Lifecycle Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel '.Str::random(4),
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts '.Str::random(4),
            'slug' => 'shirts-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $shopName,
            'slug' => Str::slug($shopName).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => $productName,
            'slug' => Str::slug($productName).'-'.Str::random(6),
            'status' => 'active',
        ]);

        return [$merchantUser, $shop, $product];
    }

    private function createImage(Product $product): ProductImage
    {
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
        $product->forceFill(['primary_image_id' => $image->getKey()])->save();

        return $image;
    }

    private function createPublishableVariant(Product $product): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => $product->product_name,
            'mrp' => 1000,
            'selling_price' => 900,
            'stock_quantity' => 5,
            'low_stock_threshold' => 1,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);
    }

    private function createMerchantUser(string $email): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => $email,
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleId = DB::table('auth_roles')->where('slug', 'merchant')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Merchant',
                'slug' => 'merchant',
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

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin-lifecycle-'.Str::random(6).'@example.test',
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
}
