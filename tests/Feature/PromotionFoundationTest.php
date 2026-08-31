<?php

namespace Tests\Feature;

use App\Models\Collection as ProductCollection;
use App\Models\Brand;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionReward;
use App\Models\PromotionTemplate;
use App\Models\PromotionTarget;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MasterData\PromotionTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class PromotionFoundationTest extends TestCase
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

    public function test_required_promotion_templates_are_seeded_idempotently_and_global(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $firstCount = PromotionTemplate::query()->count();
        $this->seed(PromotionTemplateSeeder::class);

        $this->assertSame(9, $firstCount);
        $this->assertSame(9, PromotionTemplate::query()->count());
        $this->assertTrue(Schema::hasColumns('promotion_templates', [
            'code',
            'name',
            'description',
            'example',
            'help_text',
            'reward_type',
            'required_fields',
            'configurable_fields',
            'sort_order',
            'status',
        ]));
        $this->assertFalse(Schema::hasColumn('promotion_templates', 'shop_id'));

        foreach ([
            'percentage_discount',
            'fixed_discount',
            'fixed_price',
            'fixed_bundle_price',
            'buy_x_get_y_free',
            'buy_x_get_y_discount',
            'quantity_discount',
            'tier_pricing',
            'free_gift',
        ] as $code) {
            $this->assertDatabaseHas('promotion_templates', ['code' => $code, 'status' => 'active']);
        }
    }

    public function test_merchant_can_create_offer_for_active_shop_with_percentage_reward_and_product_target(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-create@example.test');
        $product = $this->product($fixture, 'Promo Shirt');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'name' => 'Diwali Promo',
                'status' => Promotion::STATUS_ACTIVE,
                'target_scope' => 'products',
                'product_ids' => [$product->getKey()],
                'value_percent' => '20',
                'max_discount_amount' => '300',
            ]))
            ->assertRedirect();

        $promotion = Promotion::query()->where('slug', 'diwali-promo')->firstOrFail();
        $this->assertSame($fixture['merchant']->getKey(), $promotion->merchant_id);
        $this->assertSame($fixture['shop']->getKey(), $promotion->shop_id);
        $this->assertSame(
            PromotionTemplate::query()->where('code', 'percentage_discount')->value('id'),
            $promotion->promotion_template_id,
        );
        $this->assertDatabaseHas('promotion_rewards', [
            'promotion_id' => $promotion->getKey(),
            'reward_type' => PromotionReward::TYPE_PERCENTAGE_DISCOUNT,
            'value_percent' => 20,
            'max_discount_amount' => 300,
        ]);
        $this->assertDatabaseHas('promotion_targets', [
            'promotion_id' => $promotion->getKey(),
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_PRODUCT,
            'target_id' => $product->getKey(),
        ]);
    }

    public function test_merchant_cannot_edit_another_shop_promotion_and_soft_delete_sets_deleted_at(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $first = $this->fixture('promo-owner@example.test');
        $second = $this->fixture('promo-other@example.test');
        $promotion = $this->promotion($second, 'Other Offer');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->put(route('merchant.promotions.update', $promotion), $this->payload('fixed_discount'))
            ->assertNotFound();

        $this->actingAs($second['user'])
            ->withSession(['active_shop_id' => $second['shop']->getKey()])
            ->delete(route('merchant.promotions.destroy', $promotion))
            ->assertRedirect(route('merchant.promotions.index'));

        $this->assertSoftDeleted('promotions', ['id' => $promotion->getKey()]);
    }

    public function test_slug_uniqueness_is_scoped_to_shop(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $first = $this->fixture('promo-slug-a@example.test');
        $second = $this->fixture('promo-slug-b@example.test');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', ['name' => 'Clearance Offer']))
            ->assertRedirect();
        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', ['name' => 'Clearance Offer']))
            ->assertRedirect();
        $this->actingAs($second['user'])
            ->withSession(['active_shop_id' => $second['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', ['name' => 'Clearance Offer']))
            ->assertRedirect();

        $this->assertDatabaseHas('promotions', ['shop_id' => $first['shop']->getKey(), 'slug' => 'clearance-offer']);
        $this->assertDatabaseHas('promotions', ['shop_id' => $first['shop']->getKey(), 'slug' => 'clearance-offer-2']);
        $this->assertDatabaseHas('promotions', ['shop_id' => $second['shop']->getKey(), 'slug' => 'clearance-offer']);
    }

    public function test_status_dates_and_policy_validation(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-validation@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->from(route('merchant.promotions.create'))
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'refund_policy_mode' => Promotion::POLICY_ALLOWED,
                'refund_window_days' => null,
            ]))
            ->assertSessionHasErrors(['ends_at', 'refund_window_days']);

        $promotion = $this->promotion($fixture, 'Scheduled Offer', [
            'status' => Promotion::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
            'exchange_policy_mode' => Promotion::POLICY_ALLOWED,
            'exchange_window_days' => 7,
        ]);

        $this->assertSame('Scheduled', $promotion->lifecycleLabel());
        $this->assertSame(true, $promotion->exchangeAllowedOverride());
    }

    public function test_targets_are_shop_scoped_and_all_target_uses_null_id(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $first = $this->fixture('promo-target-a@example.test');
        $second = $this->fixture('promo-target-b@example.test');
        $otherProduct = $this->product($second, 'Other Product');
        $ownCollection = $this->collection($first, 'Own Collection');
        $otherCollection = $this->collection($second, 'Other Collection');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->from(route('merchant.promotions.create'))
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                'target_scope' => 'products',
                'product_ids' => [$otherProduct->getKey()],
            ]))
            ->assertSessionHasErrors('product_ids');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->from(route('merchant.promotions.create'))
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                'target_scope' => 'collections',
                'collection_ids' => [$otherCollection->getKey()],
            ]))
            ->assertSessionHasErrors('collection_ids');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                'target_scope' => 'collections',
                'collection_ids' => [$ownCollection->getKey()],
            ]))
            ->assertRedirect();

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                'name' => 'All Products Offer',
                'target_scope' => 'all',
            ]))
            ->assertRedirect();

        $promotion = Promotion::query()->where('slug', 'all-products-offer')->firstOrFail();
        $this->assertDatabaseHas('promotion_targets', [
            'promotion_id' => $promotion->getKey(),
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'target_id' => null,
        ]);
    }

    public function test_merchant_can_create_eligible_promotion_targeting_active_shop_brand(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-brand-eligible@example.test');
        $brand = $this->brand('Levis Brand');
        $this->product($fixture, 'Brand Shirt', ['brand_id' => $brand->getKey()]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'name' => 'Brand Percent Offer',
                'target_scope' => 'brands',
                'brand_ids' => [$brand->getKey()],
                'value_percent' => 20,
            ]))
            ->assertRedirect();

        $promotion = Promotion::query()->where('slug', 'brand-percent-offer')->firstOrFail();
        $this->assertDatabaseHas('promotion_targets', [
            'promotion_id' => $promotion->getKey(),
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_BRAND,
            'target_id' => $brand->getKey(),
        ]);
    }

    public function test_buy_and_get_targets_can_use_brands(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-brand-buy-get@example.test');
        $buyBrand = $this->brand('Buy Brand');
        $getBrand = $this->brand('Get Brand');
        $this->product($fixture, 'Buy Brand Product', ['brand_id' => $buyBrand->getKey()]);
        $this->product($fixture, 'Get Brand Product', ['brand_id' => $getBrand->getKey()]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('buy_x_get_y_discount', [
                'name' => 'Brand Buy Get',
                'buy_target_scope' => 'brands',
                'buy_brand_ids' => [$buyBrand->getKey()],
                'get_target_scope' => 'brands',
                'get_brand_ids' => [$getBrand->getKey()],
                'buy_quantity' => 2,
                'get_quantity' => 1,
                'value_percent' => 50,
            ]))
            ->assertRedirect();

        $promotion = Promotion::query()->where('slug', 'brand-buy-get')->firstOrFail();
        $this->assertDatabaseHas('promotion_targets', [
            'promotion_id' => $promotion->getKey(),
            'target_role' => PromotionTarget::ROLE_BUY,
            'target_type' => PromotionTarget::TYPE_BRAND,
            'target_id' => $buyBrand->getKey(),
        ]);
        $this->assertDatabaseHas('promotion_targets', [
            'promotion_id' => $promotion->getKey(),
            'target_role' => PromotionTarget::ROLE_GET,
            'target_type' => PromotionTarget::TYPE_BRAND,
            'target_id' => $getBrand->getKey(),
        ]);
    }

    public function test_brand_targets_must_be_represented_by_active_shop_products(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $first = $this->fixture('promo-brand-scope-a@example.test');
        $second = $this->fixture('promo-brand-scope-b@example.test');
        $unusedBrand = $this->brand('Unused Brand');
        $otherShopBrand = $this->brand('Other Shop Brand');
        $inactiveBrand = $this->brand('Inactive Brand', ['status' => 'inactive']);
        $this->product($second, 'Other Brand Product', ['brand_id' => $otherShopBrand->getKey()]);
        $this->product($first, 'Inactive Brand Product', ['brand_id' => $inactiveBrand->getKey()]);

        foreach ([$unusedBrand, $otherShopBrand, $inactiveBrand] as $brand) {
            $this->actingAs($first['user'])
                ->withSession(['active_shop_id' => $first['shop']->getKey()])
                ->from(route('merchant.promotions.create'))
                ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                    'target_scope' => 'brands',
                    'brand_ids' => [$brand->getKey()],
                ]))
                ->assertSessionHasErrors('brand_ids');
        }
    }

    public function test_buy_get_roles_and_reward_quantities_persist(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-buy-get@example.test');
        $buyCategory = $fixture['category'];
        $getCategory = ProductCategory::query()->create([
            'parent_id' => $fixture['shop']->root_product_category_id,
            'name' => 'Trousers '.Str::random(5),
            'slug' => 'trousers-'.Str::random(8),
            'status' => 'active',
        ]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('buy_x_get_y_discount', [
                'name' => 'Buy Shirts Get Trousers',
                'buy_target_scope' => 'categories',
                'buy_category_ids' => [$buyCategory->getKey()],
                'get_target_scope' => 'categories',
                'get_category_ids' => [$getCategory->getKey()],
                'buy_quantity' => 2,
                'get_quantity' => 1,
                'value_percent' => 50,
            ]))
            ->assertRedirect();

        $promotion = Promotion::query()->where('slug', 'buy-shirts-get-trousers')->firstOrFail();
        $this->assertDatabaseHas('promotion_rewards', [
            'promotion_id' => $promotion->getKey(),
            'reward_type' => PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT,
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'value_percent' => 50,
        ]);
        $this->assertDatabaseHas('promotion_targets', ['promotion_id' => $promotion->getKey(), 'target_role' => 'buy', 'target_id' => $buyCategory->getKey()]);
        $this->assertDatabaseHas('promotion_targets', ['promotion_id' => $promotion->getKey(), 'target_role' => 'get', 'target_id' => $getCategory->getKey()]);
    }

    public function test_reward_types_store_expected_configuration(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-rewards@example.test');
        $gift = $this->product($fixture, 'Gift Product');

        $cases = [
            ['fixed_discount', ['name' => 'Fixed Off', 'value_amount' => 500], ['reward_type' => 'fixed_discount', 'value_amount' => 500]],
            ['fixed_price', ['name' => 'Fixed Price', 'value_amount' => 799], ['reward_type' => 'fixed_price', 'value_amount' => 799]],
            ['fixed_bundle_price', ['name' => 'Bundle Price', 'bundle_quantity' => 10, 'bundle_price' => 5000], ['reward_type' => 'fixed_bundle_price', 'bundle_quantity' => 10, 'bundle_price' => 5000]],
            ['buy_x_get_y_free', ['name' => 'BOGO Free', 'buy_quantity' => 1, 'get_quantity' => 1], ['reward_type' => 'buy_x_get_y_free', 'buy_quantity' => 1, 'get_quantity' => 1]],
            ['quantity_discount', ['name' => 'Quantity Off', 'minimum_quantity' => 3, 'value_type' => 'percent', 'value_percent' => 10], ['reward_type' => 'quantity_discount', 'value_type' => 'percent', 'value_percent' => 10]],
            ['tier_pricing', ['name' => 'Tier Prices', 'minimum_quantity' => 1, 'tier_config' => [['min_quantity' => 1, 'unit_price' => 500], ['min_quantity' => 3, 'unit_price' => 450]]], ['reward_type' => 'tier_pricing']],
            ['free_gift', ['name' => 'Custom Free Gift Offer', 'minimum_eligible_subtotal' => 2000, 'gift_product_ids' => [$gift->getKey()]], ['reward_type' => 'free_gift']],
        ];

        foreach ($cases as [$code, $overrides, $expected]) {
            $this->actingAs($fixture['user'])
                ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
                ->post(route('merchant.promotions.store'), $this->payload($code, $overrides))
                ->assertRedirect();

            $promotion = Promotion::query()->where('name', $overrides['name'])->firstOrFail();
            $this->assertDatabaseHas('promotion_rewards', ['promotion_id' => $promotion->getKey(), ...$expected]);
        }

        $tierReward = Promotion::query()->where('name', 'Tier Prices')->firstOrFail()->rewards()->firstOrFail();
        $this->assertSame(450, $tierReward->tier_config[1]['unit_price']);
        $freeGift = Promotion::query()->where('name', 'Custom Free Gift Offer')->firstOrFail();
        $this->assertDatabaseHas('promotion_conditions', [
            'promotion_id' => $freeGift->getKey(),
            'condition_type' => 'minimum_eligible_subtotal',
            'value_numeric' => 2000,
        ]);
        $this->assertDatabaseHas('promotion_targets', [
            'promotion_id' => $freeGift->getKey(),
            'target_role' => 'gift',
            'target_type' => 'product',
            'target_id' => $gift->getKey(),
        ]);
    }

    public function test_coupon_codes_are_unique_per_shop_but_reusable_across_shops(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $first = $this->fixture('promo-coupon-a@example.test');
        $second = $this->fixture('promo-coupon-b@example.test');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'name' => 'Save First',
                'activation_type' => Promotion::ACTIVATION_COUPON,
                'coupon_code' => 'SAVE20',
                'value_percent' => 20,
            ]))
            ->assertRedirect();

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->from(route('merchant.promotions.create'))
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'name' => 'Save Duplicate',
                'activation_type' => Promotion::ACTIVATION_COUPON,
                'coupon_code' => 'SAVE20',
                'value_percent' => 20,
            ]))
            ->assertSessionHasErrors('coupon_code');

        $this->actingAs($second['user'])
            ->withSession(['active_shop_id' => $second['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'name' => 'Save Second Shop',
                'activation_type' => Promotion::ACTIVATION_COUPON,
                'coupon_code' => 'SAVE20',
                'value_percent' => 20,
            ]))
            ->assertRedirect();

        $this->assertSame(2, DB::table('promotion_coupons')->where('code', 'SAVE20')->count());
    }

    public function test_activation_and_policy_validation_match_refined_form_behavior(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-ui-validation@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'name' => 'Automatic No Coupon',
                'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
                'coupon_code' => null,
                'value_percent' => 20,
            ]))
            ->assertRedirect();

        $automatic = Promotion::query()->where('slug', 'automatic-no-coupon')->firstOrFail();
        $this->assertSame(0, $automatic->coupons()->count());

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->from(route('merchant.promotions.create'))
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'activation_type' => Promotion::ACTIVATION_COUPON,
                'coupon_code' => null,
                'value_percent' => 20,
            ]))
            ->assertSessionHasErrors('coupon_code');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->from(route('merchant.promotions.create'))
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                'refund_policy_mode' => Promotion::POLICY_NOT_ALLOWED,
                'refund_window_days' => null,
                'exchange_policy_mode' => Promotion::POLICY_ALLOWED,
                'exchange_window_days' => null,
            ]))
            ->assertSessionHasErrors('exchange_window_days')
            ->assertSessionDoesntHaveErrors('refund_window_days');
    }

    public function test_irrelevant_reward_values_are_not_persisted_for_selected_template(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-ui-normalize@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('percentage_discount', [
                'name' => 'Percent Clean',
                'value_percent' => 15,
                'value_amount' => 500,
                'buy_quantity' => 2,
                'bundle_price' => 999,
            ]))
            ->assertRedirect();

        $reward = Promotion::query()->where('slug', 'percent-clean')->firstOrFail()->rewards()->firstOrFail();
        $this->assertSame(PromotionReward::TYPE_PERCENTAGE_DISCOUNT, $reward->reward_type);
        $this->assertSame('15.00', $reward->value_percent);
        $this->assertNull($reward->value_amount);
        $this->assertNull($reward->buy_quantity);
        $this->assertNull($reward->bundle_price);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('quantity_discount', [
                'name' => 'Quantity Percent Clean',
                'minimum_quantity' => 3,
                'value_type' => 'percent',
                'value_percent' => 10,
                'value_amount' => 250,
            ]))
            ->assertRedirect();

        $quantityReward = Promotion::query()->where('slug', 'quantity-percent-clean')->firstOrFail()->rewards()->firstOrFail();
        $this->assertSame('percent', $quantityReward->value_type);
        $this->assertSame('10.00', $quantityReward->value_percent);
        $this->assertNull($quantityReward->value_amount);
    }

    public function test_update_without_template_id_keeps_existing_template_and_configuration_editable(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-immutable-template-no-id@example.test');
        $promotion = $this->promotion($fixture, 'Immutable Fixed Offer');
        $promotion->rewards()->create([
            'reward_type' => PromotionReward::TYPE_FIXED_DISCOUNT,
            'value_amount' => 100,
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'sort_order' => 10,
        ]);
        $templateId = $promotion->promotion_template_id;

        $payload = $this->payload('fixed_discount', [
            'name' => 'Immutable Fixed Offer Updated',
            'origin' => Promotion::ORIGIN_SYSTEM,
            'value_amount' => 200,
        ]);
        unset($payload['promotion_template_id']);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->put(route('merchant.promotions.update', $promotion), $payload)
            ->assertRedirect(route('merchant.promotions.edit', $promotion));

        $promotion->refresh();
        $this->assertSame($templateId, $promotion->promotion_template_id);
        $this->assertSame(Promotion::ORIGIN_MERCHANT, $promotion->origin);
        $this->assertSame('200.00', $promotion->rewards()->firstOrFail()->value_amount);
    }

    public function test_tampered_update_cannot_change_template_or_use_wrong_template_validation(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-immutable-template-tamper@example.test');
        $promotion = $this->promotion($fixture, 'Tamper Fixed Offer');
        $promotion->rewards()->create([
            'reward_type' => PromotionReward::TYPE_FIXED_DISCOUNT,
            'value_amount' => 100,
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'sort_order' => 10,
        ]);
        $fixedTemplateId = $promotion->promotion_template_id;
        $percentageTemplateId = PromotionTemplate::query()->where('code', 'percentage_discount')->value('id');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->from(route('merchant.promotions.edit', $promotion))
            ->put(route('merchant.promotions.update', $promotion), $this->payload('percentage_discount', [
                'promotion_template_id' => $percentageTemplateId,
                'name' => 'Tampered Percent Payload',
                'value_amount' => null,
                'value_percent' => 20,
            ]))
            ->assertRedirect(route('merchant.promotions.edit', $promotion))
            ->assertSessionHasErrors('value_amount');

        $promotion->refresh();
        $this->assertSame($fixedTemplateId, $promotion->promotion_template_id);
        $this->assertSame(PromotionReward::TYPE_FIXED_DISCOUNT, $promotion->rewards()->firstOrFail()->reward_type);
    }

    public function test_create_and_edit_offer_forms_load_template_driven_ui_values(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('promo-ui-form@example.test');
        $brand = $this->brand('Editable Brand');
        $this->product($fixture, 'Editable Brand Product', ['brand_id' => $brand->getKey()]);
        $promotion = $this->promotion($fixture, 'Editable UI Offer', [
            'activation_type' => Promotion::ACTIVATION_COUPON,
            'refund_policy_mode' => Promotion::POLICY_ALLOWED,
            'refund_window_days' => 3,
        ]);
        $promotion->rewards()->create([
            'reward_type' => PromotionReward::TYPE_FIXED_DISCOUNT,
            'value_amount' => 500,
        ]);
        $promotion->coupons()->create([
            'shop_id' => $fixture['shop']->getKey(),
            'code' => 'EDIT500',
            'status' => 'active',
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_BRAND,
            'target_id' => $brand->getKey(),
            'sort_order' => 10,
        ]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.promotions.create'))
            ->assertOk()
            ->assertSee('Choose Offer Type')
            ->assertSee('name="promotion_template_id"', false)
            ->assertSee('Percentage Discount')
            ->assertSee('Fixed Amount Discount')
            ->assertSee('Offer Preview')
            ->assertSee('Activation & Usage', false)
            ->assertSee('Leave blank to start immediately')
            ->assertSee('Leave blank for unlimited');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.promotions.edit', $promotion))
            ->assertOk()
            ->assertSee('Offer Type')
            ->assertDontSee('Choose Offer Type')
            ->assertSee('Fixed Amount Discount')
            ->assertSee('Give customers a fixed rupee discount on eligible products.')
            ->assertSee('Rs. 500 OFF')
            ->assertSee('To use a different offer type, create a new offer.')
            ->assertDontSee('name="promotion_template_id"', false)
            ->assertDontSee('Percentage Discount')
            ->assertDontSee('Tier / Bulk Pricing')
            ->assertSee('Editable UI Offer')
            ->assertSee('EDIT500')
            ->assertSee('value="brands" selected', false)
            ->assertSee('Editable Brand')
            ->assertSee('value="'.$brand->getKey().'" selected', false)
            ->assertSee('value="500.00"', false)
            ->assertSee('value="3"', false);
    }

    public function test_promotion_foundation_schema_has_no_return_allowed_column(): void
    {
        $this->assertTrue(Schema::hasColumns('promotions', [
            'uuid',
            'merchant_id',
            'shop_id',
            'promotion_template_id',
            'activation_type',
            'origin',
            'refund_policy_mode',
            'refund_window_days',
            'exchange_policy_mode',
            'exchange_window_days',
        ]));
        $this->assertFalse(Schema::hasColumn('promotions', 'return_allowed'));
        $this->assertTrue(Schema::hasColumns('promotion_redemptions', [
            'promotion_id',
            'promotion_coupon_id',
            'order_id',
            'customer_id',
            'shop_id',
            'discount_amount',
            'status',
            'redeemed_at',
            'cancelled_at',
        ]));
    }

    private function payload(string $templateCode, array $overrides = []): array
    {
        $template = PromotionTemplate::query()->where('code', $templateCode)->firstOrFail();

        return [
            'promotion_template_id' => $template->getKey(),
            'name' => 'Offer '.Str::random(6),
            'description' => 'Foundation offer',
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'target_scope' => 'all',
            'buy_target_scope' => 'all',
            'get_target_scope' => 'all',
            'value_amount' => $templateCode === 'fixed_discount' ? 100 : null,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
            ...$overrides,
        ];
    }

    private function promotion(array $fixture, string $name, array $overrides = []): Promotion
    {
        $template = PromotionTemplate::query()->where('code', 'fixed_discount')->firstOrFail();

        return Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
            ...$overrides,
        ]);
    }

    private function collection(array $fixture, string $name): ProductCollection
    {
        return ProductCollection::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => ProductCollection::STATUS_ACTIVE,
            'sort_order' => 0,
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

    private function brand(string $name, array $overrides = []): Brand
    {
        return Brand::query()->create([
            'name' => $name.' '.Str::random(5),
            'slug' => Str::slug($name).'-'.Str::random(8),
            'status' => 'active',
            'sort_order' => 0,
            ...$overrides,
        ]);
    }

    private function variant(Product $product): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'sku' => 'SKU-'.Str::random(8),
            'mrp' => 1000,
            'selling_price' => 900,
            'stock_quantity' => 5,
            'is_default' => true,
            'status' => 'active',
        ]);
    }

    private function fixture(string $email): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => $email,
            'mobile' => '90100'.random_int(10000, 99999),
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
            'business_name' => 'Promotion Merchant '.Str::random(5),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Fashion '.Str::random(5),
            'slug' => 'fashion-'.Str::random(8),
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
            'name' => 'Promotion Shop '.Str::random(5),
            'slug' => 'promotion-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return compact('user', 'merchant', 'shop', 'category');
    }
}
