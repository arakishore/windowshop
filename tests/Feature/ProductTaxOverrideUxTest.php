<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\MerchantProfile;
use App\Models\MerchantTaxSetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\TaxClass;
use App\Models\User;
use Database\Seeders\MasterData\LocationSeeder;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ProductTaxOverrideUxTest extends TestCase
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

    public function test_product_saved_with_inherit(): void
    {
        [$admin, $product, $taxClass] = $this->fixture();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'tax_mode' => 'inherit',
                'tax_class_id' => $taxClass->getKey(),
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame('inherit', $product->tax_mode);
        $this->assertNull($product->tax_class_id);
    }

    public function test_product_saved_with_override(): void
    {
        [$admin, $product, $taxClass] = $this->fixture();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'tax_mode' => 'override',
                'tax_class_id' => $taxClass->getKey(),
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame('override', $product->tax_mode);
        $this->assertSame($taxClass->getKey(), $product->tax_class_id);
    }

    public function test_product_saved_with_exempt(): void
    {
        [$admin, $product, $taxClass] = $this->fixture();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'tax_mode' => 'exempt',
                'tax_class_id' => $taxClass->getKey(),
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame('exempt', $product->tax_mode);
        $this->assertNull($product->tax_class_id);
    }

    public function test_override_requires_tax_class(): void
    {
        [$admin, $product] = $this->fixture();

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'tax_mode' => 'override',
                'tax_class_id' => null,
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('tax_class_id');
    }

    public function test_inactive_tax_class_rejected(): void
    {
        [$admin, $product] = $this->fixture();
        $inactiveTaxClass = $this->createTaxClass('GST_12', 'GST 12%', TaxClass::STATUS_INACTIVE);

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'tax_mode' => 'override',
                'tax_class_id' => $inactiveTaxClass->getKey(),
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('tax_class_id');
    }

    public function test_deleted_tax_class_rejected(): void
    {
        [$admin, $product] = $this->fixture();
        $deletedTaxClass = $this->createTaxClass('GST_28', 'GST 28%');
        $deletedTaxClass->delete();

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'tax_mode' => 'override',
                'tax_class_id' => $deletedTaxClass->getKey(),
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('tax_class_id');
    }

    public function test_product_edit_preserves_existing_configuration(): void
    {
        [$admin, $product, $taxClass] = $this->fixture();
        $product->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $taxClass->getKey(),
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Tax Configuration')
            ->assertSee('value="override" checked', false)
            ->assertSee('value="'.$taxClass->getKey().'"', false)
            ->assertSee('selected', false)
            ->assertSee('Effective Tax')
            ->assertSee($taxClass->name);
    }

    public function test_product_tax_configuration_is_hidden_when_merchant_tax_is_disabled(): void
    {
        [$admin, $product, $taxClass] = $this->fixture(taxEnabled: false);
        $product->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $taxClass->getKey(),
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertDontSee('Tax Configuration')
            ->assertDontSee('name="tax_mode"', false);

        $payload = $this->payload($product);
        unset($payload['tax_mode'], $payload['tax_class_id']);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame('override', $product->tax_mode);
        $this->assertSame($taxClass->getKey(), $product->tax_class_id);
    }

    public function test_product_list_shows_tax_column(): void
    {
        [$admin, $product, $taxClass] = $this->fixture();
        $product->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $taxClass->getKey(),
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('<th>Tax</th>', false)
            ->assertSee($taxClass->name);

        $product->forceFill([
            'tax_mode' => 'inherit',
            'tax_class_id' => null,
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Default');

        $product->forceFill([
            'tax_mode' => 'exempt',
            'tax_class_id' => null,
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Tax Exempt');
    }

    public function test_product_tax_dropdown_displays_seeded_gst_slab_classes(): void
    {
        $this->seed(LocationSeeder::class);
        $this->seed(TaxSeeder::class);

        $admin = $this->createAdminUser();
        $merchant = MerchantProfile::query()->create([
            'user_id' => $admin->getKey(),
            'business_name' => 'Seeded Tax Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel '.Str::random(4),
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $gst5 = TaxClass::query()->where('code', 'GST_5')->firstOrFail();
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts '.Str::random(4),
            'slug' => 'shirts-'.Str::random(6),
            'default_tax_class_id' => $gst5->getKey(),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Seeded Tax Shop '.Str::random(4),
            'slug' => 'seeded-tax-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
        MerchantTaxSetting::query()->create([
            'merchant_id' => $merchant->getKey(),
            'tax_enabled' => true,
            'default_tax_class_id' => $gst5->getKey(),
            'prices_include_tax' => true,
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Seeded Tax Shirt '.Str::random(4),
            'slug' => 'seeded-tax-shirt-'.Str::random(6),
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Tax Configuration')
            ->assertDontSee('Goods and Services Tax');

        foreach (['GST 0%', 'GST 0.25%', 'GST 1.5%', 'GST 3%', 'GST 5%', 'GST 18%', 'GST 28%', 'GST 40%'] as $label) {
            $response->assertSee($label);
        }

        preg_match_all('/<option[^>]*data-tax-summary="[^"]*"[^>]*>(.*?)<\/option>/s', $response->getContent(), $matches);

        $this->assertSame([
            'GST_0 / GST 0% - 0.0000% (CGST 0.0000% + SGST 0.0000%)',
            'GST_025 / GST 0.25% - 0.2500% (CGST 0.1250% + SGST 0.1250%)',
            'GST_15 / GST 1.5% - 1.5000% (CGST 0.7500% + SGST 0.7500%)',
            'GST_3 / GST 3% - 3.0000% (CGST 1.5000% + SGST 1.5000%)',
            'GST_5 / GST 5% - 5.0000% (CGST 2.5000% + SGST 2.5000%)',
            'GST_18 / GST 18% - 18.0000% (CGST 9.0000% + SGST 9.0000%)',
            'GST_28 / GST 28% - 28.0000% (CGST 14.0000% + SGST 14.0000%)',
            'GST_40 / GST 40% - 40.0000% (CGST 20.0000% + SGST 20.0000%)',
        ], array_map('trim', $matches[1]));

        $response
            ->assertSee('GST_5 / GST 5% - 5.0000% (CGST 2.5000% + SGST 2.5000%)')
            ->assertSee('GST 5% (5.0000% (CGST 2.5000% + SGST 2.5000%))');
    }

    /**
     * @return array{0: User, 1: Product, 2: TaxClass}
     */
    private function fixture(bool $taxEnabled = true): array
    {
        $admin = $this->createAdminUser();
        $merchant = MerchantProfile::query()->create([
            'user_id' => $admin->getKey(),
            'business_name' => 'Tax Demo Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel '.Str::random(4),
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $taxClass = $this->createTaxClass('GST_5', 'GST 5%');
        MerchantTaxSetting::query()->create([
            'merchant_id' => $merchant->getKey(),
            'tax_enabled' => $taxEnabled,
            'default_tax_class_id' => $taxEnabled ? $taxClass->getKey() : null,
            'prices_include_tax' => true,
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts '.Str::random(4),
            'slug' => 'shirts-'.Str::random(6),
            'default_tax_class_id' => $taxClass->getKey(),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Tax Demo Shop '.Str::random(4),
            'slug' => 'tax-demo-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Taxable Shirt '.Str::random(4),
            'slug' => 'taxable-shirt-'.Str::random(6),
            'status' => 'draft',
        ]);

        return [$admin, $product, $taxClass];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'shop_id' => $product->shop_id,
            'product_category_id' => $product->product_category_id,
            'brand_id' => null,
            'product_name' => $product->product_name,
            'short_description' => null,
            'status' => 'draft',
            'tax_mode' => 'inherit',
            'tax_class_id' => null,
        ], $overrides);
    }

    private function createTaxClass(string $code, string $name, string $status = TaxClass::STATUS_ACTIVE): TaxClass
    {
        $country = LocCountry::query()->where('iso3', 'IND')->first();

        if (! $country) {
            $country = new LocCountry();
            $country->name = 'India';
            $country->iso3 = 'IND';
            $country->iso2 = 'IN';
            $country->status = true;
            $country->save();
        }

        return TaxClass::query()->create([
            'country_id' => $country->getKey(),
            'code' => $code,
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tax Product Admin',
            'email' => 'tax-product-admin-'.Str::random(6).'@example.test',
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
