<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTaxComponent;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MasterData\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class OrderTaxSnapshotSchemaTest extends TestCase
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

        $this->seed(LocationSeeder::class);
    }

    public function test_existing_order_item_records_remain_valid_after_snapshot_migration(): void
    {
        $item = $this->orderItem();

        $this->assertFalse($item->fresh()->tax_enabled);
        $this->assertNull($item->fresh()->tax_resolution_source);
        $this->assertNull($item->fresh()->taxable_amount);
        $this->assertSame('300.00', $item->fresh()->line_subtotal);
        $this->assertSame('0.00', $item->fresh()->line_tax);
    }

    public function test_taxed_order_item_can_store_all_snapshot_fields(): void
    {
        $item = $this->orderItem([
            'tax_enabled' => true,
            'tax_resolution_source' => 'product_override',
            'tax_class_id' => 999001,
            'tax_class_code' => 'GST_18',
            'tax_class_name' => 'GST 18%',
            'tax_rate_id' => 999101,
            'tax_rate_name' => 'GST 18%',
            'tax_rate' => '18.0000',
            'price_mode' => 'exclusive',
            'taxable_amount' => '270.00',
            'line_subtotal' => '300.00',
            'line_discount' => '30.00',
            'line_tax' => '48.60',
            'line_total' => '318.60',
        ])->fresh();

        $this->assertTrue($item->tax_enabled);
        $this->assertSame('product_override', $item->tax_resolution_source);
        $this->assertSame(999001, $item->tax_class_id);
        $this->assertSame(999101, $item->tax_rate_id);
        $this->assertSame('18.0000', $item->tax_rate);
        $this->assertSame('270.00', $item->taxable_amount);
        $this->assertSame('48.60', $item->line_tax);
    }

    public function test_multiple_component_snapshots_can_be_attached_to_one_order_item(): void
    {
        $item = $this->orderItem();

        $item->taxComponents()->create([
            'tax_component_id' => 7001,
            'component_code' => 'CGST',
            'component_name' => 'CGST',
            'jurisdiction_type' => 'central',
            'rate' => '9.0000',
            'amount' => '24.30',
            'sort_order' => 20,
        ]);
        $item->taxComponents()->create([
            'tax_component_id' => 7002,
            'component_code' => 'SGST',
            'component_name' => 'SGST',
            'jurisdiction_type' => 'state',
            'rate' => '9.0000',
            'amount' => '24.30',
            'sort_order' => 10,
        ]);

        $components = $item->fresh()->taxComponents;

        $this->assertCount(2, $components);
        $this->assertSame(['SGST', 'CGST'], $components->pluck('component_code')->all());
        $this->assertTrue($components->first()->orderItem->is($item));
    }

    public function test_deleting_order_item_cascades_to_component_snapshot_rows(): void
    {
        $item = $this->orderItem();
        $component = $item->taxComponents()->create([
            'tax_component_id' => 7001,
            'component_code' => 'CGST',
            'component_name' => 'CGST',
            'jurisdiction_type' => 'central',
            'rate' => '2.5000',
            'amount' => '5.00',
            'sort_order' => 10,
        ]);

        $item->delete();

        $this->assertDatabaseMissing('order_item_tax_components', ['id' => $component->getKey()]);
    }

    public function test_snapshot_master_ids_do_not_require_master_records(): void
    {
        $item = $this->orderItem([
            'tax_enabled' => true,
            'tax_class_id' => 123456789,
            'tax_rate_id' => 987654321,
            'tax_rate' => '5.0000',
            'taxable_amount' => '100.00',
        ]);
        $component = $item->taxComponents()->create([
            'tax_component_id' => 555555,
            'component_code' => 'LEGACY',
            'component_name' => 'Legacy Component',
            'jurisdiction_type' => null,
            'rate' => '5.0000',
            'amount' => '5.00',
            'sort_order' => 0,
        ]);

        $this->assertSame(123456789, $item->fresh()->tax_class_id);
        $this->assertSame(987654321, $item->fresh()->tax_rate_id);
        $this->assertSame(555555, $component->fresh()->tax_component_id);
    }

    public function test_existing_financial_columns_are_still_used_and_not_duplicated(): void
    {
        $columns = Schema::getColumnListing('order_items');

        foreach (['line_subtotal', 'line_discount', 'line_tax', 'line_total'] as $column) {
            $this->assertSame(1, collect($columns)->filter(fn (string $value): bool => $value === $column)->count());
        }

        foreach (['discount_amount', 'tax_amount'] as $duplicateColumn) {
            $this->assertFalse(Schema::hasColumn('order_items', $duplicateColumn));
        }
    }

    public function test_snapshot_decimal_values_retain_expected_precision(): void
    {
        $item = $this->orderItem([
            'tax_rate' => '0.1250',
            'taxable_amount' => '123.45',
        ]);
        $component = $item->taxComponents()->create([
            'tax_component_id' => null,
            'component_code' => 'MICRO',
            'component_name' => 'Micro Tax',
            'jurisdiction_type' => 'local',
            'rate' => '0.1250',
            'amount' => '0.15',
            'sort_order' => 1,
        ]);

        $this->assertSame('0.1250', $item->fresh()->tax_rate);
        $this->assertSame('123.45', $item->fresh()->taxable_amount);
        $this->assertSame('0.1250', $component->fresh()->rate);
        $this->assertSame('0.15', $component->fresh()->amount);
    }

    public function test_model_relationships_work_correctly(): void
    {
        $item = $this->orderItem();
        $component = OrderItemTaxComponent::query()->create([
            'order_item_id' => $item->getKey(),
            'tax_component_id' => 7001,
            'component_code' => 'CGST',
            'component_name' => 'CGST',
            'jurisdiction_type' => 'central',
            'rate' => '2.5000',
            'amount' => '5.00',
            'sort_order' => 10,
        ]);

        $this->assertTrue($item->taxComponents()->first()->is($component));
        $this->assertTrue($component->orderItem->is($item));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function orderItem(array $overrides = []): OrderItem
    {
        return $this->order()->items()->create(array_merge([
            'product_name' => 'Snapshot Shirt',
            'variant_name' => 'Default',
            'quantity' => 2,
            'unit_mrp' => '180.00',
            'unit_price' => '150.00',
            'unit_discount' => '0.00',
            'line_subtotal' => '300.00',
            'line_discount' => '0.00',
            'line_tax' => '0.00',
            'line_total' => '300.00',
        ], $overrides));
    }

    private function order(): Order
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Snapshot User',
            'email' => 'snapshot-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Snapshot Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Snapshot Root',
            'slug' => 'snapshot-root-'.Str::random(8),
            'status' => 'active',
        ]);
        $countryId = LocCountry::query()->where('iso2', 'IN')->value('id');
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Snapshot Shop',
            'slug' => 'snapshot-shop-'.Str::random(8),
            'address_line_1' => 'Market Road',
            'country_id' => $countryId,
            'status' => 'active',
        ]);

        return Order::query()->create([
            'order_number' => 'ORD-SNAPSHOT-'.Str::upper(Str::random(8)),
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'created_source' => Order::SOURCE_POS,
            'fulfilment_type' => Order::FULFILMENT_COUNTER,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => Order::PAYMENT_UNPAID,
            'currency_code' => 'INR',
            'subtotal' => '300.00',
            'grand_total' => '300.00',
        ]);
    }
}
