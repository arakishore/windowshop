<?php

namespace Tests\Feature;

use App\Models\Collection as ProductCollection;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantCollectionManagementTest extends TestCase
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

    public function test_merchant_can_create_collection_for_active_shop(): void
    {
        $fixture = $this->fixture('collections-create@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.collections.store'), [
                'name' => 'Diwali Sale',
                'description' => 'Festive products',
                'status' => ProductCollection::STATUS_ACTIVE,
                'sort_order' => 7,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('collections', [
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'name' => 'Diwali Sale',
            'slug' => 'diwali-sale',
            'status' => ProductCollection::STATUS_ACTIVE,
            'sort_order' => 7,
        ]);
    }

    public function test_merchant_can_update_their_collection(): void
    {
        $fixture = $this->fixture('collections-update@example.test');
        $collection = $this->collection($fixture, ['name' => 'Summer Sale']);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->put(route('merchant.collections.update', $collection), [
                'name' => 'Monsoon Sale',
                'description' => 'Updated grouping',
                'status' => ProductCollection::STATUS_INACTIVE,
                'sort_order' => 3,
            ])
            ->assertRedirect(route('merchant.collections.edit', $collection));

        $collection->refresh();
        $this->assertSame('Monsoon Sale', $collection->name);
        $this->assertSame('monsoon-sale', $collection->slug);
        $this->assertSame(ProductCollection::STATUS_INACTIVE, $collection->status);
        $this->assertSame(3, $collection->sort_order);
    }

    public function test_merchant_cannot_update_another_shop_collection(): void
    {
        $first = $this->fixture('collections-first@example.test');
        $second = $this->fixture('collections-second@example.test');
        $collection = $this->collection($second, ['name' => 'Other Shop Sale']);

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->put(route('merchant.collections.update', $collection), [
                'name' => 'Hijacked',
                'status' => ProductCollection::STATUS_ACTIVE,
                'sort_order' => 0,
            ])
            ->assertNotFound();

        $this->assertSame('Other Shop Sale', $collection->fresh()->name);
    }

    public function test_merchant_can_add_own_shop_products(): void
    {
        $fixture = $this->fixture('collections-add@example.test');
        $collection = $this->collection($fixture);
        $product = $this->product($fixture, 'Collection Shirt');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.collections.products.attach', $collection), [
                'product_ids' => [$product->getKey()],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('collection_products', [
            'collection_id' => $collection->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_manage_products_filters_available_products_server_side(): void
    {
        $fixture = $this->fixture('collections-filter@example.test');
        $collection = $this->collection($fixture);
        $otherCategory = ProductCategory::query()->create([
            'parent_id' => $fixture['shop']->root_product_category_id,
            'name' => 'Shoes '.Str::random(5),
            'slug' => 'shoes-'.Str::random(8),
            'status' => 'active',
        ]);
        $activeMain = $this->product($fixture, 'Filter Active Shirt');
        $draftMain = $this->product($fixture, 'Filter Draft Shirt', ['status' => 'draft']);
        $inactiveMain = $this->product($fixture, 'Filter Inactive Shirt', ['status' => 'inactive']);
        $activeOtherCategory = $this->product($fixture, 'Filter Active Shoes', [
            'product_category_id' => $otherCategory->getKey(),
        ]);
        $selectedProduct = $this->product($fixture, 'Filter Selected Shirt');
        $collection->products()->attach($selectedProduct);

        $defaultResponse = $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.collections.products', $collection))
            ->assertOk();

        $defaultAvailableIds = $defaultResponse->viewData('availableProducts')->pluck('id')->all();
        $this->assertContains($activeMain->getKey(), $defaultAvailableIds);
        $this->assertContains($activeOtherCategory->getKey(), $defaultAvailableIds);
        $this->assertNotContains($draftMain->getKey(), $defaultAvailableIds);
        $this->assertNotContains($inactiveMain->getKey(), $defaultAvailableIds);
        $this->assertNotContains($selectedProduct->getKey(), $defaultAvailableIds);

        $filteredResponse = $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.collections.products', $collection).'?search=Filter&category_id='.$otherCategory->getKey().'&status=')
            ->assertOk();

        $filteredAvailableIds = $filteredResponse->viewData('availableProducts')->pluck('id')->all();
        $this->assertSame([$activeOtherCategory->getKey()], $filteredAvailableIds);
        $this->assertSame('', $filteredResponse->viewData('filters')['status']);
        $this->assertArrayHasKey($fixture['category']->getKey(), $filteredResponse->viewData('categoryOptions')->all());
        $this->assertArrayHasKey($otherCategory->getKey(), $filteredResponse->viewData('categoryOptions')->all());
    }

    public function test_merchant_cannot_add_another_shop_product(): void
    {
        $first = $this->fixture('collections-own@example.test');
        $second = $this->fixture('collections-cross@example.test');
        $collection = $this->collection($first);
        $otherProduct = $this->product($second, 'Other Shop Product');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->from(route('merchant.collections.products', $collection))
            ->post(route('merchant.collections.products.attach', $collection), [
                'product_ids' => [$otherProduct->getKey()],
            ])
            ->assertSessionHasErrors('product_ids.0');

        $this->assertDatabaseMissing('collection_products', [
            'collection_id' => $collection->getKey(),
            'product_id' => $otherProduct->getKey(),
        ]);
    }

    public function test_same_product_cannot_be_duplicated_in_one_collection(): void
    {
        $fixture = $this->fixture('collections-duplicate@example.test');
        $collection = $this->collection($fixture);
        $product = $this->product($fixture, 'Unique Product');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.collections.products.attach', $collection), [
                'product_ids' => [$product->getKey()],
            ])
            ->assertRedirect();

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.collections.products.attach', $collection), [
                'product_ids' => [$product->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame(1, DB::table('collection_products')
            ->where('collection_id', $collection->getKey())
            ->where('product_id', $product->getKey())
            ->count());
    }

    public function test_one_product_can_belong_to_multiple_collections(): void
    {
        $fixture = $this->fixture('collections-many@example.test');
        $first = $this->collection($fixture, ['name' => 'First Collection']);
        $second = $this->collection($fixture, ['name' => 'Second Collection']);
        $product = $this->product($fixture, 'Shared Product');

        $first->products()->attach($product);
        $second->products()->attach($product);

        $this->assertSame(2, DB::table('collection_products')
            ->where('product_id', $product->getKey())
            ->count());
    }

    public function test_removing_product_from_collection_does_not_delete_product(): void
    {
        $fixture = $this->fixture('collections-remove@example.test');
        $collection = $this->collection($fixture);
        $product = $this->product($fixture, 'Detached Product');
        $collection->products()->attach($product);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->delete(route('merchant.collections.products.detach', [$collection, $product]))
            ->assertRedirect();

        $this->assertDatabaseMissing('collection_products', [
            'collection_id' => $collection->getKey(),
            'product_id' => $product->getKey(),
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_collection_does_not_delete_products(): void
    {
        $fixture = $this->fixture('collections-delete@example.test');
        $collection = $this->collection($fixture);
        $product = $this->product($fixture, 'Remaining Product');
        $collection->products()->attach($product);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->delete(route('merchant.collections.destroy', $collection))
            ->assertRedirect(route('merchant.collections.index'));

        $this->assertSoftDeleted('collections', ['id' => $collection->getKey()]);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('collection_products', [
            'collection_id' => $collection->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_inactive_collection_remains_stored_but_is_not_active(): void
    {
        $fixture = $this->fixture('collections-inactive@example.test');
        $active = $this->collection($fixture, ['name' => 'Active Collection', 'status' => ProductCollection::STATUS_ACTIVE]);
        $inactive = $this->collection($fixture, ['name' => 'Inactive Collection', 'status' => ProductCollection::STATUS_INACTIVE]);

        $this->assertDatabaseHas('collections', [
            'id' => $inactive->getKey(),
            'status' => ProductCollection::STATUS_INACTIVE,
        ]);
        $this->assertSame([$active->getKey()], ProductCollection::query()->active()->pluck('id')->all());
    }

    public function test_slug_uniqueness_is_scoped_to_shop(): void
    {
        $first = $this->fixture('collections-slug-first@example.test');
        $second = $this->fixture('collections-slug-second@example.test');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.collections.store'), [
                'name' => 'Clearance Sale',
                'status' => ProductCollection::STATUS_ACTIVE,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.collections.store'), [
                'name' => 'Clearance Sale',
                'status' => ProductCollection::STATUS_ACTIVE,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($second['user'])
            ->withSession(['active_shop_id' => $second['shop']->getKey()])
            ->post(route('merchant.collections.store'), [
                'name' => 'Clearance Sale',
                'status' => ProductCollection::STATUS_ACTIVE,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('collections', [
            'shop_id' => $first['shop']->getKey(),
            'slug' => 'clearance-sale',
        ]);
        $this->assertDatabaseHas('collections', [
            'shop_id' => $first['shop']->getKey(),
            'slug' => 'clearance-sale-2',
        ]);
        $this->assertDatabaseHas('collections', [
            'shop_id' => $second['shop']->getKey(),
            'slug' => 'clearance-sale',
        ]);
    }

    public function test_collection_schema_uses_expected_foundation_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('collections', [
            'uuid',
            'merchant_id',
            'shop_id',
            'name',
            'slug',
            'description',
            'status',
            'sort_order',
            'created_by',
            'updated_by',
            'deleted_by',
            'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('collection_products', [
            'collection_id',
            'product_id',
            'sort_order',
        ]));
    }

    /**
     * @return array{user: User, merchant: MerchantProfile, shop: Shop, category: ProductCategory}
     */
    private function fixture(string $email): array
    {
        $user = $this->user($email);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Collection Merchant '.Str::random(5),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel '.Str::random(5),
            'slug' => 'apparel-'.Str::random(8),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts '.Str::random(5),
            'slug' => 'shirts-'.Str::random(8),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Collection Shop '.Str::random(5),
            'slug' => 'collection-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return compact('user', 'merchant', 'shop', 'category');
    }

    private function user(string $email): User
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

    private function collection(array $fixture, array $overrides = []): ProductCollection
    {
        $name = $overrides['name'] ?? 'Collection '.Str::random(5);

        return ProductCollection::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'description' => null,
            'status' => ProductCollection::STATUS_ACTIVE,
            'sort_order' => 0,
            ...$overrides,
        ]);
    }

    private function product(array $fixture, string $name, array $overrides = []): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['shop']->root_product_category_id,
            'product_category_id' => $fixture['category']->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(8),
            'status' => 'active',
            'published_at' => now(),
            ...$overrides,
        ]);
    }
}
