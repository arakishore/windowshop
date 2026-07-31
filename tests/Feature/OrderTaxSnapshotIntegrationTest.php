<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\LocState;
use App\Models\MerchantAddress;
use App\Models\MerchantProfile;
use App\Models\MerchantTaxSetting;
use App\Models\Order;
use App\Models\OrderTotal;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\TaxClass;
use App\Models\TaxRateComponent;
use App\Models\User;
use App\Services\Order\OrderCreationService;
use App\Services\Tax\Data\TaxResolutionResult;
use Database\Seeders\MasterData\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class OrderTaxSnapshotIntegrationTest extends TestCase
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

    public function test_merchant_tax_disabled_creates_zero_tax_snapshots_without_components(): void
    {
        $fixture = $this->fixture(taxEnabled: false);

        $order = $this->createOrder($fixture, [
            'amount_paid' => 300,
            'items' => [
                [
                    'product_variant_id' => $fixture['variants']['category']->getKey(),
                    'quantity' => 2,
                    'unit_price' => 1,
                    'line_tax' => 999,
                    'line_total' => 1,
                ],
            ],
        ]);
        $item = $order->items()->first();

        $this->assertFalse($item->tax_enabled);
        $this->assertSame(TaxResolutionResult::SOURCE_TAX_DISABLED, $item->tax_resolution_source);
        $this->assertNull($item->tax_rate);
        $this->assertSame('300.00', $item->line_subtotal);
        $this->assertSame('0.00', $item->line_tax);
        $this->assertSame('300.00', $item->line_total);
        $this->assertSame('300.00', $order->subtotal);
        $this->assertSame('0.00', $order->tax_total);
        $this->assertSame('300.00', $order->grand_total);
        $this->assertSame(0, $item->taxComponents()->count());
    }

    public function test_exclusive_pricing_adds_tax_and_persists_components_and_totals(): void
    {
        $fixture = $this->fixture(pricesIncludeTax: false);

        $order = $this->createOrder($fixture, [
            'amount_paid' => 400,
            'items' => [
                [
                    'product_variant_id' => $fixture['variants']['category']->getKey(),
                    'quantity' => 2,
                    'discount_type' => Order::DISCOUNT_TYPE_AMOUNT,
                    'discount_value' => 30,
                ],
            ],
        ]);
        $item = $order->items()->with('taxComponents')->first();

        $this->assertSame(TaxResolutionResult::SOURCE_CATEGORY_DEFAULT, $item->tax_resolution_source);
        $this->assertSame('GST_5', $item->tax_class_code);
        $this->assertSame('5.0000', $item->tax_rate);
        $this->assertSame('300.00', $item->line_subtotal);
        $this->assertSame('30.00', $item->line_discount);
        $this->assertSame('270.00', $item->taxable_amount);
        $this->assertSame('13.50', $item->line_tax);
        $this->assertSame('283.50', $item->line_total);
        $this->assertSame('300.00', $order->subtotal);
        $this->assertSame('30.00', $order->discount_total);
        $this->assertSame('13.50', $order->tax_total);
        $this->assertSame('283.50', $order->grand_total);
        $this->assertSame(['CGST', 'SGST'], $item->taxComponents->pluck('component_code')->all());
        $this->assertSame(['2.5000', '2.5000'], $item->taxComponents->pluck('rate')->all());
        $this->assertSame(['6.75', '6.75'], $item->taxComponents->pluck('amount')->all());
    }

    public function test_inclusive_pricing_stores_included_tax_without_adding_it_again(): void
    {
        $fixture = $this->fixture(pricesIncludeTax: true);

        $order = $this->createOrder($fixture, [
            'amount_paid' => 300,
            'items' => [
                [
                    'product_variant_id' => $fixture['variants']['category']->getKey(),
                    'quantity' => 2,
                    'discount_type' => Order::DISCOUNT_TYPE_AMOUNT,
                    'discount_value' => 30,
                ],
            ],
        ]);
        $item = $order->items()->first();

        $this->assertSame('inclusive', $item->price_mode);
        $this->assertSame('257.14', $item->taxable_amount);
        $this->assertSame('12.86', $item->line_tax);
        $this->assertSame('270.00', $item->line_total);
        $this->assertSame('12.86', $order->tax_total);
        $this->assertSame('270.00', $order->grand_total);
    }

    public function test_product_override_merchant_fallback_product_exempt_and_multiple_rates_are_saved(): void
    {
        $fixture = $this->fixture(categoryDefault: false, pricesIncludeTax: false);
        $fixture['products']['override']->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $fixture['taxClasses']['gst18']->getKey(),
        ])->save();
        $fixture['products']['exempt']->forceFill(['tax_mode' => 'exempt'])->save();

        $order = $this->createOrder($fixture, [
            'amount_paid' => 1000,
            'items' => [
                ['product_variant_id' => $fixture['variants']['merchant']->getKey(), 'quantity' => 1],
                ['product_variant_id' => $fixture['variants']['override']->getKey(), 'quantity' => 1],
                ['product_variant_id' => $fixture['variants']['exempt']->getKey(), 'quantity' => 1],
            ],
        ]);
        $items = $order->items()->with('taxComponents')->get()->keyBy('product_variant_id');

        $merchantItem = $items[$fixture['variants']['merchant']->getKey()];
        $overrideItem = $items[$fixture['variants']['override']->getKey()];
        $exemptItem = $items[$fixture['variants']['exempt']->getKey()];

        $this->assertSame(TaxResolutionResult::SOURCE_MERCHANT_DEFAULT, $merchantItem->tax_resolution_source);
        $this->assertSame('GST_18', $merchantItem->tax_class_code);
        $this->assertSame('18.00', $merchantItem->line_tax);
        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_OVERRIDE, $overrideItem->tax_resolution_source);
        $this->assertSame('GST_18', $overrideItem->tax_class_code);
        $this->assertSame('36.00', $overrideItem->line_tax);
        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_EXEMPT, $exemptItem->tax_resolution_source);
        $this->assertSame('0.00', $exemptItem->line_tax);
        $this->assertSame(0, $exemptItem->taxComponents->count());
        $this->assertSame('650.00', $order->subtotal);
        $this->assertSame('54.00', $order->tax_total);
        $this->assertSame('704.00', $order->grand_total);
    }

    public function test_order_totals_preserve_shipping_cash_rounding_amount_paid_and_change(): void
    {
        $fixture = $this->fixture(pricesIncludeTax: false);

        $order = $this->createOrder($fixture, [
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'cash_rounding' => ['method' => 'up', 'applyTo' => ['cash']],
            'amount_paid' => 350,
            'items' => [
                [
                    'product_variant_id' => $fixture['variants']['category']->getKey(),
                    'quantity' => 2,
                    'discount_type' => Order::DISCOUNT_TYPE_AMOUNT,
                    'discount_value' => 30,
                ],
            ],
            'totals' => [
                ['code' => OrderTotal::CODE_SHIPPING, 'title' => 'Shipping Charges', 'amount' => 50, 'sort_order' => 50, 'source' => 'shipping'],
            ],
        ]);

        $this->assertSame('300.00', $order->subtotal);
        $this->assertSame('30.00', $order->discount_total);
        $this->assertSame('50.00', $order->shipping_total);
        $this->assertSame('13.50', $order->tax_total);
        $this->assertSame('0.50', $order->rounding_adjustment);
        $this->assertSame('334.00', $order->grand_total);
        $this->assertSame('350.00', $order->amount_paid);
        $this->assertSame('16.00', $order->change_amount);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
    }

    public function test_submitted_financial_values_are_ignored_in_favour_of_server_pricing(): void
    {
        $fixture = $this->fixture(pricesIncludeTax: false);

        $order = $this->createOrder($fixture, [
            'subtotal' => 1,
            'tax_total' => 999,
            'grand_total' => 1,
            'amount_paid' => 1000,
            'items' => [
                [
                    'product_variant_id' => $fixture['variants']['category']->getKey(),
                    'quantity' => 1,
                    'unit_price' => 1,
                    'unit_mrp' => 1,
                    'tax_rate' => 90,
                    'taxable_amount' => 1,
                    'line_subtotal' => 1,
                    'line_tax' => 999,
                    'line_total' => 1,
                ],
            ],
        ]);
        $item = $order->items()->first();

        $this->assertSame('150.00', $item->unit_price);
        $this->assertSame('200.00', $item->unit_mrp);
        $this->assertSame('150.00', $item->line_subtotal);
        $this->assertSame('7.50', $item->line_tax);
        $this->assertSame('157.50', $item->line_total);
        $this->assertSame('150.00', $order->subtotal);
        $this->assertSame('7.50', $order->tax_total);
        $this->assertSame('157.50', $order->grand_total);
    }

    public function test_tax_snapshot_is_immutable_after_master_data_changes(): void
    {
        $fixture = $this->fixture(pricesIncludeTax: false);

        $order = $this->createOrder($fixture, [
            'amount_paid' => 200,
            'items' => [
                ['product_variant_id' => $fixture['variants']['category']->getKey(), 'quantity' => 1],
            ],
        ]);
        $item = $order->items()->with('taxComponents')->first();
        $component = $item->taxComponents->first();

        $fixture['taxClasses']['gst5']->forceFill(['code' => 'GST_5_RENAMED', 'name' => 'Changed'])->save();
        $fixture['taxClasses']['gst5']->rates()->first()->forceFill(['name' => 'Changed Rate', 'total_rate' => '9.0000'])->save();
        $component->fresh();

        $this->assertSame('GST_5', $item->fresh()->tax_class_code);
        $this->assertSame('GST 5%', $item->fresh()->tax_class_name);
        $this->assertSame('GST 5%', $item->fresh()->tax_rate_name);
        $this->assertSame('5.0000', $item->fresh()->tax_rate);
        $this->assertSame('CGST', $component->fresh()->component_code);
        $this->assertSame('2.5000', $component->fresh()->rate);
    }

    public function test_failed_tax_resolution_rolls_back_order_components_totals_and_stock(): void
    {
        $fixture = $this->fixture(pricesIncludeTax: false);
        $variant = $fixture['variants']['category'];
        $fixture['taxClasses']['gst5']->rates()->delete();

        try {
            $this->createOrder($fixture, [
                'amount_paid' => 200,
                'items' => [
                    ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
                ],
            ]);
            $this->fail('Order creation should fail when tax rate resolution fails.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('No active effective tax rate found', $exception->getMessage());
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, DB::table('order_items')->count());
        $this->assertSame(0, DB::table('order_item_tax_components')->count());
        $this->assertSame(0, DB::table('order_totals')->count());
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createOrder(array $fixture, array $data): Order
    {
        return app(OrderCreationService::class)->create(array_merge([
            'shop_id' => $fixture['shop']->getKey(),
            'amount_paid' => 0,
            'items' => [],
        ], $data), $fixture['user'])->refresh();
    }

    /**
     * @return array{
     *     user: User,
     *     merchant: MerchantProfile,
     *     shop: Shop,
     *     taxClasses: array{gst5: TaxClass, gst18: TaxClass},
     *     products: array<string, Product>,
     *     variants: array<string, ProductVariant>
     * }
     */
    private function fixture(
        bool $taxEnabled = true,
        bool $categoryDefault = true,
        bool $merchantDefault = true,
        bool $pricesIncludeTax = true,
    ): array {
        $india = LocCountry::query()->where('iso2', 'IN')->firstOrFail();
        $state = LocState::query()->where('country_id', $india->getKey())->firstOrFail();
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Order Tax User',
            'email' => 'order-tax-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Order Tax Merchant '.Str::random(6),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        MerchantAddress::query()->create([
            'merchant_id' => $merchant->getKey(),
            'address_type' => 'business',
            'address_line_1' => 'Market Road',
            'country_id' => $india->getKey(),
            'state_id' => $state->getKey(),
            'status' => 'active',
        ]);

        $gst5 = $this->taxClass($india, 'GST_5', 'GST 5%', '5.0000', '2.5000');
        $gst18 = $this->taxClass($india, 'GST_18', 'GST 18%', '18.0000', '9.0000');
        MerchantTaxSetting::query()->updateOrCreate(
            ['merchant_id' => $merchant->getKey()],
            [
                'tax_enabled' => $taxEnabled,
                'default_tax_class_id' => $merchantDefault ? $gst18->getKey() : null,
                'prices_include_tax' => $pricesIncludeTax,
            ],
        );

        $root = ProductCategory::query()->create([
            'name' => 'Order Tax Root '.Str::random(4),
            'slug' => 'order-tax-root-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Order Tax Category '.Str::random(4),
            'slug' => 'order-tax-category-'.Str::random(6),
            'default_tax_class_id' => $categoryDefault ? $gst5->getKey() : null,
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Order Tax Shop '.Str::random(4),
            'slug' => 'order-tax-shop-'.Str::random(6),
            'address_line_1' => 'Market Road',
            'country_id' => $india->getKey(),
            'state_id' => $state->getKey(),
            'status' => 'active',
        ]);

        $categoryProduct = $this->product($merchant, $shop, $root, $category, 'Category Tax Product');
        $merchantProduct = $this->product($merchant, $shop, $root, $category, 'Merchant Tax Product');
        $overrideProduct = $this->product($merchant, $shop, $root, $category, 'Override Tax Product');
        $exemptProduct = $this->product($merchant, $shop, $root, $category, 'Exempt Tax Product');

        return [
            'user' => $user,
            'merchant' => $merchant,
            'shop' => $shop,
            'taxClasses' => ['gst5' => $gst5, 'gst18' => $gst18],
            'products' => [
                'category' => $categoryProduct,
                'merchant' => $merchantProduct,
                'override' => $overrideProduct,
                'exempt' => $exemptProduct,
            ],
            'variants' => [
                'category' => $this->variant($categoryProduct, 'Category Variant', 200, 150),
                'merchant' => $this->variant($merchantProduct, 'Merchant Variant', 150, 100),
                'override' => $this->variant($overrideProduct, 'Override Variant', 250, 200),
                'exempt' => $this->variant($exemptProduct, 'Exempt Variant', 400, 350),
            ],
        ];
    }

    private function product(
        MerchantProfile $merchant,
        Shop $shop,
        ProductCategory $root,
        ProductCategory $category,
        string $name,
    ): Product {
        return Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'tax_mode' => 'inherit',
            'tax_class_id' => null,
            'status' => 'active',
        ]);
    }

    private function variant(Product $product, string $name, int $mrp, int $price): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'sku' => Str::upper(Str::slug($name)),
            'barcode' => 'BAR-'.Str::upper(Str::random(6)),
            'name' => $name,
            'mrp' => $mrp,
            'selling_price' => $price,
            'stock_quantity' => 10,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);
    }

    private function taxClass(LocCountry $country, string $code, string $name, string $totalRate, string $componentRate): TaxClass
    {
        $taxClass = TaxClass::query()->create([
            'country_id' => $country->getKey(),
            'code' => $code,
            'name' => $name,
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
        $taxRate = $taxClass->rates()->create([
            'name' => $name,
            'total_rate' => $totalRate,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => 'active',
        ]);
        $taxRate->components()->create([
            'code' => 'CGST',
            'name' => 'CGST',
            'rate' => $componentRate,
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
            'priority' => 1,
        ]);
        $taxRate->components()->create([
            'code' => 'SGST',
            'name' => 'SGST',
            'rate' => $componentRate,
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_STATE,
            'priority' => 2,
        ]);

        return $taxClass;
    }
}
