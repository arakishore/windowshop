<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\ReturnReason;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantReturnReasonManagementTest extends TestCase
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

    public function test_return_reasons_use_client_side_datatable(): void
    {
        [$user, $shop, $merchant] = $this->merchantFixture();

        ReturnReason::query()->create([
            'merchant_id' => $merchant->getKey(),
            'code' => 'custom_wrong_size',
            'name' => 'Custom wrong size',
            'sort_order' => 2,
            'restock_by_default' => true,
            'requires_manager_override' => false,
            'status' => ReturnReason::STATUS_ACTIVE,
        ]);
        ReturnReason::query()->create([
            'merchant_id' => $merchant->getKey(),
            'code' => 'custom_defective',
            'name' => 'Custom defective',
            'sort_order' => 1,
            'restock_by_default' => false,
            'requires_manager_override' => false,
            'status' => ReturnReason::STATUS_INACTIVE,
        ]);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.return-reasons.index'))
            ->assertOk()
            ->assertSee('Reasons (')
            ->assertSee('id="return-reasons-table"', false)
            ->assertSee('datatables.min.js', false)
            ->assertSee('Type to filter...')
            ->assertSee('Custom wrong size')
            ->assertSee('Custom defective')
            ->assertDontSee('name="search"', false)
            ->assertDontSee('pagination::admin-datatable');
    }

    /**
     * @return array{0: User, 1: Shop, 2: MerchantProfile}
     */
    private function merchantFixture(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => 'return-reason-merchant@example.test',
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

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Return Reason Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $root = ProductCategory::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);

        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Return Reason Shop',
            'slug' => 'return-reason-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return [$user, $shop, $merchant];
    }
}
