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
use App\Models\PromotionCondition;
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

        foreach (['buy_x_get_y_free', 'buy_x_get_y_discount', 'free_gift'] as $code) {
            $promotion = $this->promotion($fixture, $code, ['status' => Promotion::STATUS_ACTIVE]);
            $this->target($promotion, PromotionTarget::TYPE_ALL);
        }

        $line = $this->calculate($fixture)->line($fixture['variant']->getKey());

        $this->assertSame(0, $line?->promotionDiscountCents);
        $this->assertNull($line?->winningPromotion);
    }

    public function test_buy_x_get_y_free_same_pool_repeats_by_complete_whole_groups(): void
    {
        foreach ([1 => 0, 2 => 0, 3 => 100000, 4 => 100000, 5 => 100000, 6 => 200000, 9 => 300000] as $quantity => $discountCents) {
            $fixture = $this->fixture(price: 1000);
            $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
                'name' => 'Same Pool BOGO '.$quantity,
                'status' => Promotion::STATUS_ACTIVE,
            ], [
                'buy_quantity' => 2,
                'get_quantity' => 1,
            ]);
            $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_BUY);
            $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_GET);

            $line = $this->calculate($fixture, quantity: $quantity)->line($fixture['variant']->getKey());

            $this->assertSame($discountCents, $line?->promotionDiscountCents, "Failed same-pool BOGO quantity {$quantity}.");
            if ($discountCents > 0) {
                $this->assertSame($promotion->getKey(), $line?->winningPromotion?->promotionId);
                $this->assertSame(intdiv($quantity, 3), $line?->winningPromotion?->details['completed_groups']);
                $this->assertSame(intdiv($quantity, 3), $line?->winningPromotion?->details['free_quantity']);
            }
        }
    }

    public function test_buy_x_get_y_free_same_line_preserves_buy_and_get_snapshot_details(): void
    {
        $fixture = $this->fixture(price: 800);
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Same Variant BOGO',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_GET);

        $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());

        $this->assertSame(240000, $line?->baseLineSubtotalCents);
        $this->assertSame(80000, $line?->promotionDiscountCents);
        $this->assertSame(160000, $line?->finalLineSubtotalCents);
        $this->assertSame(['buy', 'get'], $line?->winningPromotion?->details['roles']);
        $this->assertSame(2, $line?->winningPromotion?->details['participating_buy_quantity']);
        $this->assertSame(1, $line?->winningPromotion?->details['free_quantity']);

        $line = $this->calculate($fixture, quantity: 6)->line($fixture['variant']->getKey());

        $this->assertSame(160000, $line?->promotionDiscountCents);
        $this->assertSame(2, $line?->winningPromotion?->details['completed_groups']);
        $this->assertSame(4, $line?->winningPromotion?->details['participating_buy_quantity']);
        $this->assertSame(2, $line?->winningPromotion?->details['free_quantity']);
    }

    public function test_buy_x_get_y_free_selects_cheapest_get_units_deterministically(): void
    {
        $fixture = $this->fixture(price: 1000);
        $productB = $this->product($fixture, 'BOGO Cheap B');
        $variantB = $this->variant($fixture, $productB, 600, 'BOGO Cheap B');
        $productC = $this->product($fixture, 'BOGO Cheap C');
        $variantC = $this->variant($fixture, $productC, 600, 'BOGO Cheap C');
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Cheapest BOGO',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_GET);

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 2],
            ['product_variant_id' => $variantB->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantC->getKey(), 'quantity' => 3],
        ]);

        $this->assertSame(120000, $result->promotionDiscountCents());
        $this->assertSame(60000, $result->line($variantB->getKey())?->promotionDiscountCents);
        $this->assertSame(60000, $result->line($variantC->getKey())?->promotionDiscountCents);
        $this->assertSame(1, $result->line($variantB->getKey())?->winningPromotion?->details['free_quantity']);
        $this->assertSame(1, $result->line($variantC->getKey())?->winningPromotion?->details['free_quantity']);
    }

    public function test_buy_x_get_y_free_supports_different_buy_and_get_pools(): void
    {
        $fixture = $this->fixture(price: 1200);
        $getProduct = $this->product($fixture, 'BOGO Gift Pool');
        $getVariant = $this->variant($fixture, $getProduct, 300, 'BOGO Gift Pool');
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Different Pool BOGO',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $fixture['product']->getKey(), PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $getProduct->getKey(), PromotionTarget::ROLE_GET);

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 5],
            ['product_variant_id' => $getVariant->getKey(), 'quantity' => 3],
        ]);

        $this->assertSame(60000, $result->promotionDiscountCents());
        $this->assertSame(0, $result->line($fixture['variant']->getKey())?->promotionDiscountCents);
        $this->assertSame(60000, $result->line($getVariant->getKey())?->promotionDiscountCents);
        $this->assertSame('different', $result->line($getVariant->getKey())?->winningPromotion?->details['pool_type']);
        $this->assertSame(4, $result->line($fixture['variant']->getKey())?->winningPromotion?->details['participating_buy_quantity']);
    }

    public function test_buy_x_get_y_free_supports_partial_overlap_without_double_counting_units(): void
    {
        $fixture = $this->fixture(price: 1000);
        $saleCollection = $this->collection($fixture);
        $fixture['product']->collections()->attach($saleCollection->getKey());
        $buyOnlyProduct = $this->product($fixture, 'BOGO Buy Only');
        $buyOnlyVariant = $this->variant($fixture, $buyOnlyProduct, 900, 'BOGO Buy Only');
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Partial Overlap BOGO',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_CATEGORY, $fixture['category']->getKey(), PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_COLLECTION, $saleCollection->getKey(), PromotionTarget::ROLE_GET);

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 1],
            ['product_variant_id' => $buyOnlyVariant->getKey(), 'quantity' => 1],
        ]);

        $this->assertSame(0, $result->promotionDiscountCents());

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 1],
            ['product_variant_id' => $buyOnlyVariant->getKey(), 'quantity' => 2],
        ]);

        $this->assertSame(100000, $result->promotionDiscountCents());
        $this->assertSame('partial_overlap', $result->line($fixture['variant']->getKey())?->winningPromotion?->details['pool_type']);
        $this->assertSame(2, $result->line($buyOnlyVariant->getKey())?->winningPromotion?->details['participating_buy_quantity']);
    }

    public function test_buy_x_get_y_free_uses_whole_units_only_for_decimal_quantities(): void
    {
        $fixture = $this->fixture(price: 800);
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Decimal BOGO',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_GET);

        $line = $this->calculate($fixture, quantity: '3.750')->line($fixture['variant']->getKey());

        $this->assertSame(300000, $line?->baseLineSubtotalCents);
        $this->assertSame(80000, $line?->promotionDiscountCents);
        $this->assertSame(220000, $line?->finalLineSubtotalCents);
        $this->assertSame('whole_units_only_fractional_remainder_base_price', $line?->winningPromotion?->details['quantity_rule']);
    }

    public function test_buy_x_get_y_free_supports_existing_target_types_for_buy_and_get_roles(): void
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
            $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
                'name' => 'Targeted BOGO '.$type.' '.Str::random(4),
                'status' => Promotion::STATUS_ACTIVE,
            ], [
                'buy_quantity' => 2,
                'get_quantity' => 1,
            ]);
            $targetId = match ($idSource) {
                'product' => $fixture['product']->getKey(),
                'variant' => $fixture['variant']->getKey(),
                'category' => $fixture['category']->getKey(),
                'brand' => $fixture['brand']->getKey(),
                'collection' => $collection->getKey(),
                default => null,
            };
            $this->target($promotion, $type, $targetId, PromotionTarget::ROLE_BUY);
            $this->target($promotion, $type, $targetId, PromotionTarget::ROLE_GET);

            $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());

            $this->assertSame(100000, $line?->promotionDiscountCents, "Failed BOGO {$type} target.");
            $this->assertSame($promotion->getKey(), $line?->winningPromotion?->promotionId);
        }
    }

    public function test_buy_x_get_y_free_does_not_leave_free_get_reward_when_buy_line_loses_conflict(): void
    {
        $fixture = $this->fixture(price: 1000);
        $getProduct = $this->product($fixture, 'BOGO Conflict Get');
        $getVariant = $this->variant($fixture, $getProduct, 500, 'BOGO Conflict Get');
        $bogo = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Conflicted BOGO',
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 1,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($bogo, PromotionTarget::TYPE_PRODUCT, $fixture['product']->getKey(), PromotionTarget::ROLE_BUY);
        $this->target($bogo, PromotionTarget::TYPE_PRODUCT, $getProduct->getKey(), PromotionTarget::ROLE_GET);
        $fixed = $this->promotion($fixture, 'fixed_discount', [
            'name' => 'Buy Line Winner',
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '100.00']);
        $this->target($fixed, PromotionTarget::TYPE_PRODUCT, $fixture['product']->getKey());

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 2],
            ['product_variant_id' => $getVariant->getKey(), 'quantity' => 1],
        ]);

        $this->assertSame(20000, $result->promotionDiscountCents());
        $this->assertSame($fixed->getKey(), $result->line($fixture['variant']->getKey())?->winningPromotion?->promotionId);
        $this->assertSame(0, $result->line($getVariant->getKey())?->promotionDiscountCents);
        $this->assertNull($result->line($getVariant->getKey())?->winningPromotion);
    }

    public function test_quantity_discount_uses_full_decimal_quantity_after_minimum_and_competes_normally(): void
    {
        $fixture = $this->fixture(price: 1000);
        $promotion = $this->promotion($fixture, 'quantity_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 5,
        ], [
            'value_type' => 'percent',
            'value_percent' => '10.00',
        ]);
        $promotion->conditions()->create([
            'condition_type' => PromotionCondition::TYPE_MINIMUM_QUANTITY,
            'operator' => '>=',
            'value_numeric' => '3.00',
            'sort_order' => 10,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture, quantity: '2.750')->line($fixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);
        $this->assertSame(275000, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());
        $this->assertSame(30000, $line?->promotionDiscountCents);
        $this->assertSame(270000, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: 4)->line($fixture['variant']->getKey());
        $this->assertSame(40000, $line?->promotionDiscountCents);
        $this->assertSame(360000, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: '3.750')->line($fixture['variant']->getKey());
        $this->assertSame(375000, $line?->baseLineSubtotalCents);
        $this->assertSame(37500, $line?->promotionDiscountCents);
        $this->assertSame(337500, $line?->finalLineSubtotalCents);

        $competing = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '150.00']);
        $this->target($competing, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());
        $this->assertSame(45000, $line?->promotionDiscountCents);
        $this->assertSame($competing->getKey(), $line?->winningPromotion?->promotionId);
    }

    public function test_quantity_discount_respects_category_brand_and_collection_targets(): void
    {
        foreach ([
            PromotionTarget::TYPE_CATEGORY => 'category',
            PromotionTarget::TYPE_BRAND => 'brand',
            PromotionTarget::TYPE_COLLECTION => 'collection',
        ] as $targetType => $idSource) {
            $fixture = $this->fixture(price: 1000);
            $collection = $this->collection($fixture);
            $fixture['product']->collections()->attach($collection->getKey());

            $promotion = $this->promotion($fixture, 'quantity_discount', [
                'status' => Promotion::STATUS_ACTIVE,
            ], [
                'value_type' => 'amount',
                'value_amount' => '75.00',
            ]);
            $promotion->conditions()->create([
                'condition_type' => PromotionCondition::TYPE_MINIMUM_QUANTITY,
                'operator' => '>=',
                'value_numeric' => '2.00',
                'sort_order' => 10,
            ]);
            $this->target($promotion, $targetType, match ($idSource) {
                'category' => $fixture['category']->getKey(),
                'brand' => $fixture['brand']->getKey(),
                'collection' => $collection->getKey(),
            });

            $line = $this->calculate($fixture, quantity: 2)->line($fixture['variant']->getKey());
            $this->assertSame(15000, $line?->promotionDiscountCents, "Failed matching {$targetType} target.");
            $this->assertSame($promotion->getKey(), $line?->winningPromotion?->promotionId);
        }
    }

    public function test_fixed_bundle_price_allocates_across_best_eligible_whole_units_and_repeats(): void
    {
        $fixture = $this->fixture(price: 300);
        $productB = $this->product($fixture, 'Bundle B');
        $productC = $this->product($fixture, 'Bundle C');
        $productD = $this->product($fixture, 'Bundle D');
        $variantB = $this->variant($fixture, $productB, 400, 'Bundle B');
        $variantC = $this->variant($fixture, $productC, 500, 'Bundle C');
        $variantD = $this->variant($fixture, $productD, 900, 'Bundle D');

        $promotion = $this->promotion($fixture, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '999.00',
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture, quantity: 1)->line($fixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);

        $line = $this->calculate($fixture, quantity: 2)->line($fixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);

        $line = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $variantD->getKey(), 'quantity' => 3],
        ])->line($variantD->getKey());
        $this->assertSame(270000, $line?->baseLineSubtotalCents);
        $this->assertSame(170100, $line?->promotionDiscountCents);

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantB->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantC->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantD->getKey(), 'quantity' => 1],
        ]);

        $this->assertSame(0, $result->line($fixture['variant']->getKey())?->promotionDiscountCents);
        $this->assertSame(17800, $result->line($variantB->getKey())?->promotionDiscountCents);
        $this->assertSame(22250, $result->line($variantC->getKey())?->promotionDiscountCents);
        $this->assertSame(40050, $result->line($variantD->getKey())?->promotionDiscountCents);
        $this->assertSame(80100, $result->promotionDiscountCents());
        $this->assertSame(1, $result->line($variantD->getKey())?->winningPromotion?->details['completed_bundles']);

        $repeatFixture = $this->fixture(price: 400);
        $repeat = $this->promotion($repeatFixture, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '999.00',
        ]);
        $this->target($repeat, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($repeatFixture, quantity: 6)->line($repeatFixture['variant']->getKey());
        $this->assertSame(240000, $line?->baseLineSubtotalCents);
        $this->assertSame(40200, $line?->promotionDiscountCents);
        $this->assertSame(2, $line?->winningPromotion?->details['completed_bundles']);

        $line = $this->calculate($repeatFixture, quantity: 7)->line($repeatFixture['variant']->getKey());
        $this->assertSame(280000, $line?->baseLineSubtotalCents);
        $this->assertSame(40200, $line?->promotionDiscountCents);
        $this->assertSame(2, $line?->winningPromotion?->details['completed_bundles']);

        $noBenefitFixture = $this->fixture(price: 200);
        $noBenefit = $this->promotion($noBenefitFixture, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '999.00',
        ]);
        $this->target($noBenefit, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($noBenefitFixture, quantity: 3)->line($noBenefitFixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);
        $this->assertNull($line?->winningPromotion);
    }

    public function test_fixed_bundle_allocation_distributes_rounding_deterministically(): void
    {
        $fixture = $this->fixture(price: 100);
        $productB = $this->product($fixture, 'Rounding Bundle B');
        $productC = $this->product($fixture, 'Rounding Bundle C');
        $variantB = $this->variant($fixture, $productB, 100, 'Rounding Bundle B');
        $variantC = $this->variant($fixture, $productC, 100, 'Rounding Bundle C');

        $promotion = $this->promotion($fixture, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '299.99',
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantB->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantC->getKey(), 'quantity' => 1],
        ]);

        $this->assertSame(1, $result->promotionDiscountCents());
        $this->assertSame(1, $result->line($fixture['variant']->getKey())?->promotionDiscountCents);
        $this->assertSame(0, $result->line($variantB->getKey())?->promotionDiscountCents);
        $this->assertSame(0, $result->line($variantC->getKey())?->promotionDiscountCents);
    }

    public function test_fixed_bundle_price_ignores_wrong_shop_and_can_lose_to_better_line_promotions(): void
    {
        $fixture = $this->fixture(price: 500);
        $wrongShop = $this->fixture(price: 500);
        $wrongTargetProduct = $this->product($fixture, 'Wrong Bundle Target');

        $externalBundle = $this->promotion($wrongShop, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '1.00',
        ]);
        $this->target($externalBundle, PromotionTarget::TYPE_ALL);

        $wrongTargetBundle = $this->promotion($fixture, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '1.00',
        ]);
        $this->target($wrongTargetBundle, PromotionTarget::TYPE_PRODUCT, $wrongTargetProduct->getKey());

        $bundle = $this->promotion($fixture, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 1,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '999.00',
        ]);
        $this->target($bundle, PromotionTarget::TYPE_ALL);

        $fixed = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '200.00']);
        $this->target($fixed, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());

        $this->assertSame(60000, $line?->promotionDiscountCents);
        $this->assertSame($fixed->getKey(), $line?->winningPromotion?->promotionId);
    }

    public function test_fixed_bundle_price_does_not_leave_orphaned_allocations_when_a_line_loses_to_another_promotion(): void
    {
        $fixture = $this->fixture(price: 700);
        $productB = $this->product($fixture, 'Bundle Conflict B');
        $productC = $this->product($fixture, 'Bundle Conflict C');
        $variantB = $this->variant($fixture, $productB, 700, 'Bundle Conflict B');
        $variantC = $this->variant($fixture, $productC, 700, 'Bundle Conflict C');

        $bundle = $this->promotion($fixture, 'fixed_bundle_price', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 1,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '1500.00',
        ]);
        $this->target($bundle, PromotionTarget::TYPE_ALL);

        $fixed = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '250.00']);
        $this->target($fixed, PromotionTarget::TYPE_VARIANT, $variantB->getKey());

        $result = $this->calculateRows($fixture['shop'], [
            ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantB->getKey(), 'quantity' => 1],
            ['product_variant_id' => $variantC->getKey(), 'quantity' => 1],
        ]);

        $this->assertSame(0, $result->line($fixture['variant']->getKey())?->promotionDiscountCents);
        $this->assertSame(25000, $result->line($variantB->getKey())?->promotionDiscountCents);
        $this->assertSame($fixed->getKey(), $result->line($variantB->getKey())?->winningPromotion?->promotionId);
        $this->assertSame(0, $result->line($variantC->getKey())?->promotionDiscountCents);
    }

    public function test_tier_pricing_uses_volume_tiers_for_full_decimal_quantity(): void
    {
        $fixture = $this->fixture(price: 1000);
        $promotion = $this->promotion($fixture, 'tier_pricing', [
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'tier_config' => [
                ['min_quantity' => 3, 'unit_price' => 900],
                ['min_quantity' => 5, 'unit_price' => 850],
                ['min_quantity' => 10, 'unit_price' => 800],
            ],
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture, quantity: '2.750')->line($fixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);
        $this->assertSame(275000, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());
        $this->assertSame(30000, $line?->promotionDiscountCents);
        $this->assertSame(270000, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: '3.750')->line($fixture['variant']->getKey());
        $this->assertSame(375000, $line?->baseLineSubtotalCents);
        $this->assertSame(37500, $line?->promotionDiscountCents);
        $this->assertSame(337500, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: '4.500')->line($fixture['variant']->getKey());
        $this->assertSame(45000, $line?->promotionDiscountCents);
        $this->assertSame(405000, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: 5)->line($fixture['variant']->getKey());
        $this->assertSame(75000, $line?->promotionDiscountCents);
        $this->assertSame(425000, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: '5.250')->line($fixture['variant']->getKey());
        $this->assertSame(78750, $line?->promotionDiscountCents);
        $this->assertSame(446250, $line?->finalLineSubtotalCents);

        $line = $this->calculate($fixture, quantity: '10.750')->line($fixture['variant']->getKey());
        $this->assertSame(215000, $line?->promotionDiscountCents);
        $this->assertSame(860000, $line?->finalLineSubtotalCents);

        $tooHighFixture = $this->fixture(price: 500);
        $tooHigh = $this->promotion($tooHighFixture, 'tier_pricing', [
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'tier_config' => [['min_quantity' => 2, 'unit_price' => 600]],
        ]);
        $this->target($tooHigh, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($tooHighFixture, quantity: 2)->line($tooHighFixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);

        $malformedFixture = $this->fixture(price: 1000);
        $malformed = $this->promotion($malformedFixture, 'tier_pricing', [
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'tier_config' => [['min_quantity' => '', 'unit_price' => '']],
        ]);
        $this->target($malformed, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($malformedFixture, quantity: 3)->line($malformedFixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);

        $competing = $this->promotion($fixture, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
        ], ['value_amount' => '250.00']);
        $this->target($competing, PromotionTarget::TYPE_ALL);

        $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());
        $this->assertSame(75000, $line?->promotionDiscountCents);
        $this->assertSame($competing->getKey(), $line?->winningPromotion?->promotionId);
    }

    public function test_fixed_bundle_promotion_recalculates_before_tax_during_order_creation(): void
    {
        $fixture = $this->fixture(price: 400, withTax: true);
        $productB = $this->product($fixture, 'Tax Bundle B');
        $productC = $this->product($fixture, 'Tax Bundle C');
        $variantB = $this->variant($fixture, $productB, 500, 'Tax Bundle B');
        $variantC = $this->variant($fixture, $productC, 600, 'Tax Bundle C');
        $promotion = $this->promotion($fixture, 'fixed_bundle_price', [
            'name' => 'Taxed Bundle',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'bundle_quantity' => 3,
            'bundle_price' => '1200.00',
        ]);
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
                ['product_variant_id' => $variantB->getKey(), 'quantity' => 1],
                ['product_variant_id' => $variantC->getKey(), 'quantity' => 1],
            ],
        ], $fixture['user'])->load(['items', 'totals']);

        $this->assertSame('300.00', number_format($order->items->sum(fn ($item): float => (float) $item->line_discount), 2, '.', ''));
        $this->assertSame('1200.00', number_format($order->items->sum(fn ($item): float => (float) $item->taxable_amount), 2, '.', ''));
        $this->assertSame('60.00', number_format($order->items->sum(fn ($item): float => (float) $item->line_tax), 2, '.', ''));
        $this->assertSame('1260.00', $order->grand_total);

        $item = $order->items->firstWhere('product_variant_id', $variantC->getKey());
        $this->assertSame($promotion->getKey(), $item->metadata['promotion']['id']);
        $this->assertSame('fixed_bundle_price', $item->metadata['promotion']['reward_type']);
        $this->assertSame(1, $item->metadata['promotion']['details']['completed_bundles']);
    }

    public function test_buy_x_get_y_free_recalculates_before_tax_and_stores_buy_and_get_order_snapshots(): void
    {
        $fixture = $this->fixture(price: 1000, withTax: true);
        $getProduct = $this->product($fixture, 'Tax BOGO Get');
        $getVariant = $this->variant($fixture, $getProduct, 600, 'Tax BOGO Get');
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Taxed BOGO',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $fixture['product']->getKey(), PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $getProduct->getKey(), PromotionTarget::ROLE_GET);

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $fixture['shop']->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'amount_paid' => 0,
            'items' => [
                ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 2],
                ['product_variant_id' => $getVariant->getKey(), 'quantity' => 1],
            ],
        ], $fixture['user'])->load(['items', 'totals']);

        $buyItem = $order->items->firstWhere('product_variant_id', $fixture['variant']->getKey());
        $getItem = $order->items->firstWhere('product_variant_id', $getVariant->getKey());

        $this->assertSame('0.00', $buyItem->line_discount);
        $this->assertSame('2000.00', $buyItem->taxable_amount);
        $this->assertSame($promotion->getKey(), $buyItem->metadata['promotion']['id']);
        $this->assertSame(['buy'], $buyItem->metadata['promotion']['details']['roles']);
        $this->assertSame(2, $buyItem->metadata['promotion']['details']['participating_buy_quantity']);
        $this->assertSame(0, $buyItem->metadata['promotion']['details']['free_quantity']);

        $this->assertSame('600.00', $getItem->line_discount);
        $this->assertSame('0.00', $getItem->taxable_amount);
        $this->assertSame('0.00', $getItem->line_tax);
        $this->assertSame(['get'], $getItem->metadata['promotion']['details']['roles']);
        $this->assertSame(1, $getItem->metadata['promotion']['details']['free_quantity']);
        $this->assertSame('600.00', $getItem->metadata['promotion']['details']['promotion_discount']);
        $this->assertSame('2100.00', $order->grand_total);
    }

    public function test_buy_x_get_y_free_same_line_order_snapshot_keeps_both_roles(): void
    {
        $fixture = $this->fixture(price: 800);
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Same Line Order BOGO',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_GET);

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $fixture['shop']->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'amount_paid' => 0,
            'items' => [
                ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 3],
            ],
        ], $fixture['user'])->load(['items']);

        $item = $order->items->first();

        $this->assertSame('2400.00', $item->line_subtotal);
        $this->assertSame('800.00', $item->line_discount);
        $this->assertSame(['buy', 'get'], $item->metadata['promotion']['details']['roles']);
        $this->assertSame(2, $item->metadata['promotion']['details']['participating_buy_quantity']);
        $this->assertSame(1, $item->metadata['promotion']['details']['free_quantity']);
        $this->assertCount(2, $item->metadata['promotion']['details']['buy_units']);
        $this->assertCount(1, $item->metadata['promotion']['details']['get_units']);
    }

    public function test_coupon_bogo_is_ignored_and_pos_order_creation_remains_unaffected(): void
    {
        $fixture = $this->fixture(price: 1000);
        $promotion = $this->promotion($fixture, 'buy_x_get_y_free', [
            'name' => 'Coupon BOGO',
            'status' => Promotion::STATUS_ACTIVE,
            'activation_type' => Promotion::ACTIVATION_COUPON,
        ], [
            'buy_quantity' => 2,
            'get_quantity' => 1,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_ALL, role: PromotionTarget::ROLE_GET);

        $line = $this->calculate($fixture, quantity: 3)->line($fixture['variant']->getKey());
        $this->assertSame(0, $line?->promotionDiscountCents);

        $promotion->forceFill(['activation_type' => Promotion::ACTIVATION_AUTOMATIC])->save();
        $order = app(OrderCreationService::class)->create([
            'shop_id' => $fixture['shop']->getKey(),
            'created_source' => Order::SOURCE_POS,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_PAID,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'amount_paid' => 3000,
            'items' => [
                ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 3],
            ],
        ], $fixture['user'])->load(['items']);

        $item = $order->items->first();
        $this->assertSame('0.00', $item->line_discount);
        $this->assertNull($item->metadata);
    }

    public function test_quantity_discount_recalculates_before_tax_during_order_creation(): void
    {
        $fixture = $this->fixture(price: 1000, withTax: true);
        $promotion = $this->promotion($fixture, 'quantity_discount', [
            'name' => 'Taxed Quantity Offer',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'value_type' => 'percent',
            'value_percent' => '10.00',
        ]);
        $promotion->conditions()->create([
            'condition_type' => PromotionCondition::TYPE_MINIMUM_QUANTITY,
            'operator' => '>=',
            'value_numeric' => '3.00',
            'sort_order' => 10,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $fixture['shop']->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'amount_paid' => 0,
            'items' => [
                ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 3],
            ],
        ], $fixture['user'])->load(['items', 'totals']);

        $item = $order->items->first();
        $this->assertSame('3000.00', $item->line_subtotal);
        $this->assertSame('300.00', $item->line_discount);
        $this->assertSame('2700.00', $item->taxable_amount);
        $this->assertSame('135.00', $item->line_tax);
        $this->assertSame('2835.00', $item->line_total);
        $this->assertSame('quantity_discount', $item->metadata['promotion']['reward_type']);
        $this->assertSame('2835.00', $order->grand_total);
    }

    public function test_tier_pricing_recalculates_before_tax_during_order_creation(): void
    {
        $fixture = $this->fixture(price: 1000, withTax: true);
        $promotion = $this->promotion($fixture, 'tier_pricing', [
            'name' => 'Taxed Tier Offer',
            'status' => Promotion::STATUS_ACTIVE,
        ], [
            'tier_config' => [
                ['min_quantity' => 3, 'unit_price' => 900],
                ['min_quantity' => 5, 'unit_price' => 850],
            ],
        ]);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $fixture['shop']->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'amount_paid' => 0,
            'items' => [
                ['product_variant_id' => $fixture['variant']->getKey(), 'quantity' => 5],
            ],
        ], $fixture['user'])->load(['items', 'totals']);

        $item = $order->items->first();
        $this->assertSame('5000.00', $item->line_subtotal);
        $this->assertSame('750.00', $item->line_discount);
        $this->assertSame('4250.00', $item->taxable_amount);
        $this->assertSame('212.50', $item->line_tax);
        $this->assertSame('4462.50', $item->line_total);
        $this->assertSame('tier_pricing', $item->metadata['promotion']['reward_type']);
        $this->assertSame('4462.50', $order->grand_total);
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

    private function calculate(array $fixture, ?Customer $customer = null, int|float|string $quantity = 1)
    {
        return app(PromotionCalculator::class)->calculateForShop($fixture['shop'], [[
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => $quantity,
        ]], $customer);
    }

    private function calculateRows(Shop $shop, array $rows, ?Customer $customer = null)
    {
        return app(PromotionCalculator::class)->calculateForShop($shop, $rows, $customer);
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

    private function variant(array $fixture, Product $product, int $price, string $name = 'Variant'): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'availability_status_id' => $product->availability_status_id,
            'sku' => 'SKU-'.Str::random(8),
            'name' => $name,
            'mrp' => $price + 100,
            'selling_price' => $price,
            'stock_quantity' => 20,
            'is_default' => false,
            'is_sellable' => true,
            'status' => 'active',
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

    private function target(Promotion $promotion, string $type, ?int $id = null, string $role = PromotionTarget::ROLE_ELIGIBLE): void
    {
        $promotion->targets()->create([
            'target_role' => $role,
            'target_type' => $type,
            'target_id' => $id,
            'sort_order' => 10,
        ]);
    }
}
