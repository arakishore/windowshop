<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\Shop;
use App\Models\User;
use App\Services\Promotion\ShopPromotionStarterService;
use Database\Seeders\MasterData\PromotionStarterSeeder;
use Database\Seeders\MasterData\PromotionTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class PromotionStarterTest extends TestCase
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

    public function test_origin_column_defaults_to_merchant_and_merchants_cannot_submit_system_origin(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-origin@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.promotions.store'), $this->payload('fixed_discount', [
                'name' => 'Merchant Origin Offer',
                'origin' => Promotion::ORIGIN_SYSTEM,
            ]))
            ->assertRedirect();

        $promotion = Promotion::query()->where('slug', 'merchant-origin-offer')->firstOrFail();
        $this->assertSame(Promotion::ORIGIN_MERCHANT, $promotion->origin);

        $starter = $this->starter($fixture, 'fixed_discount');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->put(route('merchant.promotions.update', $starter), $this->payload('fixed_discount', [
                'name' => 'Edited Starter Origin',
                'origin' => Promotion::ORIGIN_MERCHANT,
                'value_amount' => 250,
            ]))
            ->assertRedirect();

        $this->assertSame(Promotion::ORIGIN_SYSTEM, $starter->fresh()->origin);
    }

    public function test_service_creates_one_system_starter_per_active_template_and_is_idempotent(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-idempotent@example.test');

        $this->assertSame(9, Promotion::query()
            ->where('shop_id', $fixture['shop']->getKey())
            ->where('origin', Promotion::ORIGIN_SYSTEM)
            ->count());

        app(ShopPromotionStarterService::class)->createMissingSystemStartersForShop($fixture['shop']);

        $this->assertSame(9, Promotion::query()
            ->where('shop_id', $fixture['shop']->getKey())
            ->where('origin', Promotion::ORIGIN_SYSTEM)
            ->count());

        $template = PromotionTemplate::query()->where('code', 'fixed_discount')->firstOrFail();
        Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => 'Merchant Fixed One',
            'slug' => 'merchant-fixed-one',
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'origin' => Promotion::ORIGIN_MERCHANT,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
        ]);
        Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => 'Merchant Fixed Two',
            'slug' => 'merchant-fixed-two',
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'origin' => Promotion::ORIGIN_MERCHANT,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
        ]);

        $this->assertSame(2, Promotion::query()
            ->where('shop_id', $fixture['shop']->getKey())
            ->where('promotion_template_id', $template->getKey())
            ->where('origin', Promotion::ORIGIN_MERCHANT)
            ->count());
    }

    public function test_starter_service_does_not_overwrite_edited_system_starters(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-no-overwrite@example.test');
        $starter = $this->starter($fixture, 'percentage_discount');

        $starter->forceFill(['name' => 'Merchant Edited Starter'])->save();
        $starter->rewards()->firstOrFail()->forceFill(['value_percent' => '25.00'])->save();

        app(ShopPromotionStarterService::class)->createMissingSystemStartersForShop($fixture['shop']);

        $starter->refresh();
        $this->assertSame('Merchant Edited Starter', $starter->name);
        $this->assertSame('25.00', $starter->rewards()->firstOrFail()->value_percent);
    }

    public function test_system_starters_are_not_deletable_but_merchant_offers_are(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-delete@example.test');
        $starter = $this->starter($fixture, 'fixed_discount');
        $merchant = $this->merchantPromotion($fixture, 'Merchant Delete Offer');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->delete(route('merchant.promotions.destroy', $starter))
            ->assertRedirect(route('merchant.promotions.index'))
            ->assertSessionHas('error');

        $this->assertFalse($starter->fresh()->trashed());

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->delete(route('merchant.promotions.destroy', $merchant))
            ->assertRedirect(route('merchant.promotions.index'));

        $this->assertSoftDeleted('promotions', ['id' => $merchant->getKey()]);
    }

    public function test_complete_starters_can_be_edited_and_activated_but_incomplete_starters_cannot_activate(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-activate@example.test');
        $completeStarter = $this->starter($fixture, 'percentage_discount');
        $incompleteStarter = $this->starter($fixture, 'fixed_price');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->put(route('merchant.promotions.update', $completeStarter), $this->payload('percentage_discount', [
                'name' => 'Edited Ten Percent',
                'status' => Promotion::STATUS_INACTIVE,
                'value_percent' => 15,
            ]))
            ->assertRedirect();

        $this->assertSame(Promotion::ORIGIN_SYSTEM, $completeStarter->fresh()->origin);
        $this->assertSame('Edited Ten Percent', $completeStarter->fresh()->name);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->patch(route('merchant.promotions.toggle-status', $completeStarter))
            ->assertRedirect();

        $this->assertSame(Promotion::STATUS_ACTIVE, $completeStarter->fresh()->status);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->from(route('merchant.promotions.index'))
            ->patch(route('merchant.promotions.toggle-status', $incompleteStarter))
            ->assertRedirect(route('merchant.promotions.index'))
            ->assertSessionHasErrors('promotion');

        $this->assertSame(Promotion::STATUS_INACTIVE, $incompleteStarter->fresh()->status);
        $this->assertSame('Needs Setup', $incompleteStarter->fresh()->load(['template', 'rewards', 'conditions', 'targets'])->setupStatusLabel());
    }

    public function test_index_uses_quick_offer_labels_buttons_and_merchant_friendly_validity(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-index-ui@example.test');
        $scheduledStarter = $this->starter($fixture, 'percentage_discount');
        $hiddenSlug = $scheduledStarter->slug;

        $scheduledStarter->forceFill([
            'status' => Promotion::STATUS_ACTIVE,
            'starts_at' => '2026-09-01 09:00:00',
            'ends_at' => null,
        ])->save();

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.promotions.index'))
            ->assertOk()
            ->assertSee('Offers')
            ->assertSee('Quick Offers')
            ->assertSee('My Offers')
            ->assertSee('Quick Offer')
            ->assertDontSee('Starter Offer')
            ->assertDontSee($hiddenSlug)
            ->assertSee('Set Up')
            ->assertSee('Edit')
            ->assertSee('Activate')
            ->assertSee('Deactivate')
            ->assertSee('Needs Setup')
            ->assertSee('No schedule')
            ->assertSee('Started 01 Sep 2026', false)
            ->assertSee('No end date');
    }

    public function test_all_starter_templates_can_be_opened_and_activation_respects_setup_completeness(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-all-templates@example.test');
        $readyTemplates = ['percentage_discount', 'fixed_discount', 'quantity_discount'];
        $setupTemplates = [
            'fixed_price',
            'fixed_bundle_price',
            'buy_x_get_y_free',
            'buy_x_get_y_discount',
            'tier_pricing',
            'free_gift',
        ];

        foreach ([...$readyTemplates, ...$setupTemplates] as $templateCode) {
            $starter = $this->starter($fixture, $templateCode);

            $this->actingAs($fixture['user'])
                ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
                ->get(route('merchant.promotions.edit', $starter))
                ->assertOk()
                ->assertSee($starter->name);
        }

        foreach ($readyTemplates as $templateCode) {
            $starter = $this->starter($fixture, $templateCode);

            $this->actingAs($fixture['user'])
                ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
                ->patch(route('merchant.promotions.toggle-status', $starter))
                ->assertRedirect();

            $this->assertSame(Promotion::STATUS_ACTIVE, $starter->fresh()->status);
        }

        foreach ($setupTemplates as $templateCode) {
            $starter = $this->starter($fixture, $templateCode);

            $this->actingAs($fixture['user'])
                ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
                ->from(route('merchant.promotions.index'))
                ->patch(route('merchant.promotions.toggle-status', $starter))
                ->assertRedirect(route('merchant.promotions.index'))
                ->assertSessionHasErrors('promotion');

            $this->assertSame(Promotion::STATUS_INACTIVE, $starter->fresh()->status);
        }
    }

    public function test_edit_uses_same_read_only_offer_type_rule_for_quick_and_custom_offers(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-edit-readonly@example.test');
        $quickOffer = $this->starter($fixture, 'fixed_discount');
        $customOffer = $this->merchantPromotion($fixture, 'Custom Fixed Readonly Offer');

        foreach ([$quickOffer, $customOffer] as $promotion) {
            $this->actingAs($fixture['user'])
                ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
                ->get(route('merchant.promotions.edit', $promotion))
                ->assertOk()
                ->assertSee('Offer Type')
                ->assertSee('Fixed Amount Discount')
                ->assertSee('Give customers a fixed rupee discount on eligible products.')
                ->assertSee('Rs. 500 OFF')
                ->assertSee('To use a different offer type, create a new offer.')
                ->assertDontSee('Choose Offer Type')
                ->assertDontSee('name="promotion_template_id"', false)
                ->assertDontSee('Percentage Discount')
                ->assertDontSee('Tier / Bulk Pricing');
        }
    }

    public function test_existing_shops_are_backfilled_by_starter_seeder(): void
    {
        $fixture = $this->fixture('starter-backfill@example.test');
        $this->assertSame(0, Promotion::query()->where('shop_id', $fixture['shop']->getKey())->count());

        $this->seed(PromotionTemplateSeeder::class);
        $this->seed(PromotionStarterSeeder::class);

        $this->assertSame(9, Promotion::query()
            ->where('shop_id', $fixture['shop']->getKey())
            ->where('origin', Promotion::ORIGIN_SYSTEM)
            ->count());
    }

    public function test_new_shops_receive_starters_automatically_when_templates_exist(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-new-shop@example.test');

        $this->assertSame(9, Promotion::query()
            ->where('shop_id', $fixture['shop']->getKey())
            ->where('origin', Promotion::ORIGIN_SYSTEM)
            ->count());
    }

    public function test_starter_defaults_have_no_fake_dates_and_only_complete_defaults_are_complete(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->fixture('starter-defaults@example.test');

        $percentage = $this->starter($fixture, 'percentage_discount')->load(['template', 'rewards', 'targets']);
        $fixed = $this->starter($fixture, 'fixed_discount')->load(['template', 'rewards', 'targets']);
        $quantity = $this->starter($fixture, 'quantity_discount')->load(['template', 'rewards', 'conditions', 'targets']);
        $bogo = $this->starter($fixture, 'buy_x_get_y_free')->load(['template', 'rewards', 'targets']);

        $this->assertNull($percentage->starts_at);
        $this->assertNull($percentage->ends_at);
        $this->assertSame(Promotion::STATUS_INACTIVE, $percentage->status);
        $this->assertSame('10.00', $percentage->rewards->first()->value_percent);
        $this->assertSame(PromotionTarget::TYPE_ALL, $percentage->targets->first()->target_type);
        $this->assertTrue($percentage->isSetupComplete());

        $this->assertSame('100.00', $fixed->rewards->first()->value_amount);
        $this->assertTrue($fixed->isSetupComplete());

        $this->assertSame('3.00', $quantity->conditions->firstWhere('condition_type', PromotionCondition::TYPE_MINIMUM_QUANTITY)->value_numeric);
        $this->assertSame('percent', $quantity->rewards->first()->value_type);
        $this->assertSame('10.00', $quantity->rewards->first()->value_percent);
        $this->assertTrue($quantity->isSetupComplete());

        $this->assertSame(1, $bogo->rewards->first()->buy_quantity);
        $this->assertSame(1, $bogo->rewards->first()->get_quantity);
        $this->assertFalse($bogo->isSetupComplete());
        $this->assertSame('Needs Setup', $bogo->setupStatusLabel());
    }

    public function test_promotion_schema_has_origin_but_no_preset_code(): void
    {
        $this->assertTrue(Schema::hasColumn('promotions', 'origin'));
        $this->assertFalse(Schema::hasColumn('promotions', 'preset_code'));
    }

    private function payload(string $templateCode, array $overrides = []): array
    {
        $template = PromotionTemplate::query()->where('code', $templateCode)->firstOrFail();

        return [
            'promotion_template_id' => $template->getKey(),
            'name' => 'Offer '.Str::random(6),
            'description' => 'Starter offer test',
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'target_scope' => 'all',
            'buy_target_scope' => 'all',
            'get_target_scope' => 'all',
            'value_amount' => $templateCode === 'fixed_discount' ? 100 : null,
            'value_percent' => $templateCode === 'percentage_discount' ? 10 : null,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
            ...$overrides,
        ];
    }

    private function starter(array $fixture, string $templateCode): Promotion
    {
        return Promotion::query()
            ->where('shop_id', $fixture['shop']->getKey())
            ->where('origin', Promotion::ORIGIN_SYSTEM)
            ->whereHas('template', fn ($query) => $query->where('code', $templateCode))
            ->firstOrFail();
    }

    private function merchantPromotion(array $fixture, string $name): Promotion
    {
        $template = PromotionTemplate::query()->where('code', 'fixed_discount')->firstOrFail();
        $promotion = Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'origin' => Promotion::ORIGIN_MERCHANT,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
        ]);

        $promotion->rewards()->create([
            'reward_type' => PromotionReward::TYPE_FIXED_DISCOUNT,
            'value_amount' => '100.00',
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'sort_order' => 10,
        ]);

        return $promotion;
    }

    private function fixture(string $email): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => $email,
            'mobile' => '90200'.random_int(10000, 99999),
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
            'business_name' => 'Starter Merchant '.Str::random(5),
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
            'name' => 'Starter Shop '.Str::random(5),
            'slug' => 'starter-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return compact('user', 'merchant', 'shop', 'category');
    }
}
