<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Collection as ProductCollection;
use App\Models\Customer;
use App\Models\LocCountry;
use App\Models\LocState;
use App\Models\MerchantAddress;
use App\Models\MerchantProfile;
use App\Models\MerchantTaxSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\Shop;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use App\Models\User;
use App\Services\Order\OrderCreationService;
use App\Services\Promotion\Engine\PromotionCalculator;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use Database\Seeders\MasterData\LocationSeeder;
use Database\Seeders\MasterData\PromotionTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class PromotionCalculationEngineTest extends TestCase
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

        $this->seed(PromotionTemplateSeeder::class);
    }

    public function test_percentage_discount_applies_cap_and_respects_lifecycle_and_shop(): void
    {
        $fixture = $this->fixture(price: 5000);
        $other = $this->fixture(price: 5000);

        $winner = $this->promotion($fixture, 'percentage_discount', [
            'name' => 'Capped Twenty',
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 5,
        ], [
            'value_percent' => '20.00',
            'max_discount_amount' => '600.00',
        ]);
        $this->target($winner, PromotionTarget::TYPE_ALL);

        foreach ([
            ['Inactive', Promotion::STATUS_INACTIVE, null, null],
            ['Future', Promotion::STATUS_ACTIVE, now()->addDay(), null],
            ['Expired', Promotion::STATUS_ACTIVE, now()->subDays(5), now()->subDay()],
        ] as [$name, $status, $startsAt, $endsAt]) {
            $promotion = $this->promotion($fixture, 'fixed_discount', [
                'name' => $name,
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'priority' => 100,
            ], ['value_amount' => '1000.00']);
            $this->target($promotion, PromotionTarget::TYPE_ALL);
        }

        $wrongShop = $this->promotion($other, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '2000.00']);
        $this->target($wrongShop, PromotionTarget::TYPE_ALL);

        $result = $this->calculate($fixture);
        $line = $result->line($fixture['variant']->getKey());

        $this->assertSame(500000, $line?->baseLineSubtotalCents);
        $this->assertSame(60000, $line?->promotionDiscountCents);
        $this->assertSame($winner->getKey(), $line?->winningPromotion?->promotionId);
    }

    public function test_fixed_discount_is_bounded_by_line_value_and_requires_matching_target(): void
    {
        $fixture = $this->fixture(price: 100);
        $wrongTargetProduct = $this->product($fixture, 'Wrong Target');

        $wrongTarget = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '150.00']);
        $this->target($wrongTarget, PromotionTarget::TYPE_PRODUCT, $wrongTargetProduct->getKey());

        $promotion = $this->promotion($fixture, 'fixed_discount', [
            'name' => 'Large Fixed',
            'status' => Promotion::STATUS_ACTIVE,
        ], ['value_amount' => '150.00']);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $fixture['product']->getKey());

        $line = $this->calculate($fixture)->line($fixture['variant']->getKey());

        $this->assertSame(10000, $line?->promotionDiscountCents);
        $this->assertSame(0, $line?->finalLineSubtotalCents);
        $this->assertSame($promotion->getKey(), $line?->winningPromotion?->promotionId);
    }

    public function test_fixed_price_only_applies_when_it_benefits_customer(): void
    {
        $fixture = $this->fixture(price: 700);

        $higher = $this->promotion($fixture, 'fixed_price', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '799.00']);
        $this->target($higher, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture)->line($fixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);
        $this->assertNull($line?->winningPromotion);

        $lower = $this->promotion($fixture, 'fixed_price', [
            'status' => Promotion::STATUS_ACTIVE,
        ], ['value_amount' => '650.00']);
        $this->target($lower, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture)->line($fixture['variant']->getKey());
        $this->assertSame(5000, $line?->promotionDiscountCents);
        $this->assertSame($lower->getKey(), $line?->winningPromotion?->promotionId);
    }

    public function test_target_matching_supports_all_product_variant_category_brand_and_collection(): void
    {
        foreach ([
            PromotionTarget::TYPE_ALL => null,
            PromotionTarget::TYPE_PRODUCT => 'product',
            PromotionTarget::TYPE_VARIANT => 'variant',
            PromotionTarget::TYPE_CATEGORY => 'category',
            PromotionTarget::TYPE_BRAND => 'brand',
            PromotionTarget::TYPE_COLLECTION => 'collection',
        ] as $type => $idSource) {
            $fixture = $this->fixture(price: 1000);
            $collection = $this->collection($fixture);
            $fixture['product']->collections()->attach($collection->getKey());

            $promotion = $this->promotion($fixture, 'fixed_discount', [
                'status' => Promotion::STATUS_ACTIVE,
            ], ['value_amount' => '100.00']);
            $this->target($promotion, $type, match ($idSource) {
                'product' => $fixture['product']->getKey(),
                'variant' => $fixture['variant']->getKey(),
                'category' => $fixture['category']->getKey(),
                'brand' => $fixture['brand']->getKey(),
                'collection' => $collection->getKey(),
                default => null,
            });

            $line = $this->calculate($fixture)->line($fixture['variant']->getKey());
            $this->assertSame(10000, $line?->promotionDiscountCents, "Failed matching {$type} target.");
        }
    }

    public function test_best_discount_wins_without_stacking_and_priority_breaks_ties(): void
    {
        $fixture = $this->fixture(price: 1000);

        $percent = $this->promotion($fixture, 'percentage_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_percent' => '10.00']);
        $this->target($percent, PromotionTarget::TYPE_ALL);

        $fixed = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 1,
        ], ['value_amount' => '150.00']);
        $this->target($fixed, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture)->line($fixture['variant']->getKey());
        $this->assertSame(15000, $line?->promotionDiscountCents);
        $this->assertSame($fixed->getKey(), $line?->winningPromotion?->promotionId);

        $tieLow = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 2,
        ], ['value_amount' => '200.00']);
        $this->target($tieLow, PromotionTarget::TYPE_ALL);

        $tieHigh = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 20,
        ], ['value_amount' => '200.00']);
        $this->target($tieHigh, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture)->line($fixture['variant']->getKey());
        $this->assertSame(20000, $line?->promotionDiscountCents);
        $this->assertSame($tieHigh->getKey(), $line?->winningPromotion?->promotionId);
    }

    public function test_coupon_and_unsupported_promotions_are_skipped(): void
    {
        $fixture = $this->fixture(price: 1000);

        $coupon = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'activation_type' => Promotion::ACTIVATION_COUPON,
        ], ['value_amount' => '500.00']);
        $this->target($coupon, PromotionTarget::TYPE_ALL);

        foreach (['fixed_bundle_price', 'buy_x_get_y_free', 'buy_x_get_y_discount', 'quantity_discount', 'tier_pricing', 'free_gift'] as $code) {
            $promotion = $this->promotion($fixture, $code, ['status' => Promotion::STATUS_ACTIVE]);
            $this->target($promotion, PromotionTarget::TYPE_ALL);
        }

        $line = $this->calculate($fixture)->line($fixture['variant']->getKey());

        $this->assertSame(0, $line?->promotionDiscountCents);
        $this->assertNull($line?->winningPromotion);
    }

    public function test_new_customer_only_uses_prior_completed_orders_for_same_shop(): void
    {
        $fixture = $this->fixture(price: 1000);
        $customer = Customer::query()->create([
            'name' => 'Promo Customer',
            'email' => 'promo-customer@example.test',
            'status' => Customer::STATUS_ACTIVE,
        ]);
        $promotion = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'new_customer_only' => true,
        ], ['value_amount' => '100.00']);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture, $customer)->line($fixture['variant']->getKey());
        $this->assertSame(10000, $line?->promotionDiscountCents);

        Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-PRIOR-'.Str::random(6),
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'customer_id' => $customer->getKey(),
            'order_status' => Order::STATUS_COMPLETED,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
        ]);

        $line = $this->calculate($fixture, $customer)->line($fixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);
    }

    public function test_order_creation_recalculates_promotion_before_tax_and_stores_snapshot_metadata(): void
    {
        $fixture = $this->fixture(price: 1000, withTax: true);
        $promotion = $this->promotion($fixture, 'fixed_discount', [
            'name' => 'Taxed Offer',
            'status' => Promotion::STATUS_ACTIVE,
        ], ['value_amount' => '100.00']);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $fixture['shop']->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'amount_paid' => 0,
            'items' => [
                ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 1],
            ],
        ], $fixture['user'])->load(['items', 'totals']);

        $item = $order->items->first();
        $this->assertSame('1000.00', $item->unit_price);
        $this->assertSame('100.00', $item->line_discount);
        $this->assertSame('900.00', $item->taxable_amount);
        $this->assertSame('45.00', $item->line_tax);
        $this->assertSame('945.00', $item->line_total);
        $this->assertSame($promotion->getKey(), $item->metadata['promotion']['id']);
        $this->assertSame('945.00', $order->grand_total);
    }

    private function calculate(array $fixture, ?Customer $customer = null)
    {
        return app(PromotionCalculator::class)->calculateForShop($fixture['shop'], [[
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 1,
        ]], $customer);
    }

    private function fixture(int $price = 1000, bool $withTax = false): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Promotion User',
            'email' => 'promotion-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Promotion Merchant '.Str::random(6),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        app(MerchantAvailabilityStatusSeeder::class)->seedDefaultsForMerchant($merchant);
        $root = ProductCategory::query()->create([
            'name' => 'Promotion Root '.Str::random(5),
            'slug' => 'promotion-root-'.Str::random(8),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Promotion Category '.Str::random(5),
            'slug' => 'promotion-category-'.Str::random(8),
            'status' => 'active',
        ]);

        if ($withTax) {
            $this->configureTax($merchant, $category);
        }

        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Promotion Shop '.Str::random(5),
            'slug' => 'promotion-shop-'.Str::random(8),
            'address_line_1' => 'Market Road',
            'status' => 'active',
        ]);
        $brand = Brand::query()->create([
            'name' => 'Promotion Brand '.Str::random(5),
            'slug' => 'promotion-brand-'.Str::random(8),
            'status' => 'active',
        ]);
        $product = $this->product(compact('merchant', 'shop', 'root', 'category', 'brand'), 'Promotion Product');
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $shop->getKey(),
            'availability_status_id' => $product->availability_status_id,
            'sku' => 'SKU-'.Str::random(8),
            'name' => 'Default',
            'mrp' => $price + 100,
            'selling_price' => $price,
            'stock_quantity' => 20,
            'is_default' => true,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        return compact('user', 'merchant', 'shop', 'root', 'category', 'brand', 'product', 'variant');
    }

    private function configureTax(MerchantProfile $merchant, ProductCategory $category): void
    {
        $this->seed(LocationSeeder::class);
        $country = LocCountry::query()->where('iso2', 'IN')->firstOrFail();
        $state = LocState::query()->where('country_id', $country->getKey())->firstOrFail();
        MerchantAddress::query()->create([
            'merchant_id' => $merchant->getKey(),
            'address_type' => 'business',
            'address_line_1' => 'Market Road',
            'country_id' => $country->getKey(),
            'state_id' => $state->getKey(),
            'status' => 'active',
        ]);
        $taxClass = TaxClass::query()->create([
            'country_id' => $country->getKey(),
            'code' => 'PROMO_GST_5_'.Str::upper(Str::random(4)),
            'name' => 'Promo GST 5',
            'total_rate' => '5.0000',
            'status' => 'active',
        ]);
        $rate = TaxRate::query()->create([
            'tax_class_id' => $taxClass->getKey(),
            'name' => 'Promo GST 5 Rate',
            'total_rate' => '5.0000',
            'effective_from' => now()->subDay()->toDateString(),
            'priority' => 0,
            'status' => 'active',
        ]);
        TaxRateComponent::query()->create([
            'tax_rate_id' => $rate->getKey(),
            'code' => 'GST',
            'name' => 'GST',
            'rate' => '5.0000',
            'jurisdiction_type' => 'country',
            'priority' => 1,
            'status' => 'active',
        ]);
        MerchantTaxSetting::query()->create([
            'merchant_id' => $merchant->getKey(),
            'tax_enabled' => true,
            'prices_include_tax' => false,
            'default_tax_class_id' => $taxClass->getKey(),
        ]);
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();
    }

    private function product(array $fixture, string $name): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['root']->getKey(),
            'product_category_id' => $fixture['category']->getKey(),
            'brand_id' => $fixture['brand']->getKey(),
            'availability_status_id' => DB::table('product_availability_statuses')
                ->where('merchant_id', $fixture['merchant']->getKey())
                ->where('code', ProductAvailabilityStatus::CODE_IN_STOCK)
                ->value('id'),
            'product_name' => $name.' '.Str::random(5),
            'slug' => Str::slug($name).'-'.Str::random(8),
            'status' => 'active',
            'published_at' => now(),
        ]);
    }

    private function collection(array $fixture): ProductCollection
    {
        return ProductCollection::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'name' => 'Promotion Collection '.Str::random(5),
            'slug' => 'promotion-collection-'.Str::random(8),
            'status' => ProductCollection::STATUS_ACTIVE,
        ]);
    }

    private function promotion(array $fixture, string $templateCode, array $overrides = [], array $reward = []): Promotion
    {
        $template = PromotionTemplate::query()->where('code', $templateCode)->firstOrFail();
        $promotion = Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => $overrides['name'] ?? 'Promotion '.Str::random(6),
            'slug' => Str::slug($overrides['name'] ?? 'promotion-'.Str::random(6)),
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'origin' => Promotion::ORIGIN_MERCHANT,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
            ...$overrides,
        ]);
        $promotion->rewards()->create([
            'reward_type' => $template->reward_type,
            ...$reward,
        ]);

        return $promotion;
    }

    private function target(Promotion $promotion, string $type, ?int $id = null): void
    {
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => $type,
            'target_id' => $id,
            'sort_order' => 10,
        ]);
    }
}
