<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ShopPage;
use App\Models\User;
use App\Services\Merchant\ShopPageInitializer;
use App\Services\Merchant\ShopPageTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ShopPageFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_merchant_created_shop_receives_four_standard_draft_pages(): void
    {
        [$user, $merchant, $category] = $this->fixture('merchant');

        $this->actingAs($user)->post(route('merchant.shops.store'), [
            'root_product_category_id' => $category->getKey(),
            'name' => 'Merchant Page Shop',
            'address_line_1' => 'Main Road',
            'status' => 'inactive',
        ])->assertRedirect();

        $shop = Shop::query()->where('merchant_id', $merchant->getKey())->firstOrFail();
        $this->assertStandardPages($shop);
    }

    public function test_admin_created_shop_receives_four_standard_draft_pages(): void
    {
        [$admin] = $this->fixture('super_admin');
        [, $merchant, $category] = $this->fixture('merchant');

        $this->actingAs($admin)->post(route('admin.merchants.shops.store', $merchant), [
            'root_product_category_id' => $category->getKey(),
            'name' => 'Admin Page Shop',
            'address_line_1' => 'Main Road',
            'status' => 'inactive',
        ])->assertRedirect();

        $shop = Shop::query()->where('merchant_id', $merchant->getKey())->firstOrFail();
        $this->assertStandardPages($shop);
    }

    public function test_initializer_is_idempotent_and_preserves_existing_content(): void
    {
        $shop = $this->shopFixture();
        $page = $shop->pages()->where('page_key', 'about')->firstOrFail();
        $page->update(['body' => 'Our story', 'status' => ShopPage::STATUS_PUBLISHED, 'published_at' => now()]);
        $originalUpdatedAt = $page->fresh()->updated_at;

        $initializer = app(ShopPageInitializer::class);
        $initializer->initialize($shop->getKey());
        $initializer->initialize($shop->getKey());

        $this->assertSame(4, $shop->pages()->count());
        $this->assertSame('Our story', $page->fresh()->body);
        $this->assertSame(ShopPage::STATUS_PUBLISHED, $page->fresh()->status);
        $this->assertEquals($originalUpdatedAt, $page->fresh()->updated_at);
    }

    public function test_templates_resolve_shop_name_and_available_contact_details(): void
    {
        $shop = $this->shopFixture();
        $shop->forceFill([
            'name' => 'Vana Studio',
            'email' => 'hello@vana.test',
            'mobile' => '9000000000',
        ])->save();

        $renderer = app(ShopPageTemplateService::class);
        $privacy = $renderer->render('privacy', $shop);
        $terms = $renderer->render('terms', $shop);

        $this->assertStringContainsString('Vana Studio', $privacy);
        $this->assertStringContainsString('hello@vana.test or 9000000000', $privacy);
        $this->assertStringContainsString('Vana Studio', $terms);
        $this->assertStringContainsString('hello@vana.test or 9000000000', $terms);
        $this->assertStringNotContainsString('{{shop_', $privacy.$terms);
    }

    public function test_templates_use_safe_contact_fallback_when_optional_details_are_missing(): void
    {
        $shop = $this->shopFixture();
        $renderer = app(ShopPageTemplateService::class);

        foreach (['privacy', 'terms'] as $key) {
            $body = $renderer->render($key, $shop);
            $this->assertStringContainsString("Use the contact details shown on this shop's WindowShop page.", $body);
            $this->assertStringNotContainsString('{{shop_', $body);
        }
    }

    public function test_empty_legal_pages_are_populated_without_touching_about_or_policy_notes(): void
    {
        $shop = $this->shopFixture();
        $shop->pages()->where('page_key', 'about')->update(['body' => 'My story']);
        $shop->pages()->where('page_key', 'policies')->update(['body' => 'Additional notes']);
        $shop->pages()->where('page_key', 'privacy')->update(['body' => null]);
        $shop->pages()->where('page_key', 'terms')->update(['body' => '']);

        app(ShopPageInitializer::class)->initialize($shop->getKey());

        $this->assertStringContainsString('Information received', $shop->pages()->where('page_key', 'privacy')->value('body'));
        $this->assertStringContainsString('Prices and orders', $shop->pages()->where('page_key', 'terms')->value('body'));
        $this->assertSame('My story', $shop->pages()->where('page_key', 'about')->value('body'));
        $this->assertSame('Additional notes', $shop->pages()->where('page_key', 'policies')->value('body'));
    }

    public function test_existing_non_empty_legal_content_is_preserved_on_repeated_population(): void
    {
        $shop = $this->shopFixture();
        $shop->pages()->where('page_key', 'privacy')->update(['body' => 'Merchant privacy text']);
        $shop->pages()->where('page_key', 'terms')->update(['body' => 'Merchant terms text']);
        $initializer = app(ShopPageInitializer::class);

        $initializer->initialize($shop->getKey());
        $initializer->initialize($shop->getKey());

        $this->assertSame('Merchant privacy text', $shop->pages()->where('page_key', 'privacy')->value('body'));
        $this->assertSame('Merchant terms text', $shop->pages()->where('page_key', 'terms')->value('body'));
        $this->assertSame(4, $shop->pages()->count());
    }

    public function test_standard_page_key_cannot_be_duplicated(): void
    {
        $shop = $this->shopFixture();

        $this->expectException(QueryException::class);
        $shop->pages()->create([
            'page_type' => ShopPage::TYPE_STANDARD,
            'page_key' => 'about',
            'title' => 'Another About',
            'slug' => 'another-about',
        ]);
    }

    public function test_custom_slugs_are_unique_within_each_shop(): void
    {
        $first = $this->shopFixture();
        $second = $this->shopFixture();

        foreach ([$first, $second] as $shop) {
            $shop->pages()->create([
                'page_type' => ShopPage::TYPE_CUSTOM,
                'title' => 'Size Guide',
                'slug' => 'size-guide',
                'status' => ShopPage::STATUS_DRAFT,
            ]);
        }

        $this->assertSame(2, ShopPage::query()->where('slug', 'size-guide')->count());
        $this->expectException(QueryException::class);
        $first->pages()->create([
            'page_type' => ShopPage::TYPE_CUSTOM,
            'title' => 'Duplicate Size Guide',
            'slug' => 'size-guide',
        ]);
    }

    public function test_initializer_backfills_missing_pages_for_inactive_shops_without_overwriting_content(): void
    {
        $shop = $this->shopFixture('inactive');
        $shop->pages()->where('page_key', 'terms')->update(['body' => 'Reviewed terms']);
        $shop->pages()->where('page_key', 'privacy')->delete();

        app(ShopPageInitializer::class)->initializeMany([$shop->getKey()]);

        $this->assertStandardPages($shop, false);
        $this->assertSame('Reviewed terms', $shop->pages()->where('page_key', 'terms')->value('body'));
    }

    public function test_migration_backfills_existing_active_and_inactive_shops(): void
    {
        $active = $this->shopFixture();
        $inactive = $this->shopFixture('inactive');
        $active->pages()->where('page_key', 'about')->update(['body' => 'Approved story']);
        $active->pages()->where('page_key', 'privacy')->update(['body' => 'Merchant privacy text']);
        $active->pages()->where('page_key', 'terms')->update(['body' => 'Merchant terms text']);
        $active->pages()->where('page_key', 'policies')->update(['body' => 'Merchant policy notes']);
        $inactive->pages()->delete();

        $migration = require database_path('migrations/2026_09_18_000002_backfill_shop_pages.php');
        $migration->up();
        $templateMigration = require database_path('migrations/2026_09_18_000003_populate_shop_page_templates.php');
        $templateMigration->up();

        $this->assertStandardPages($active, false);
        $this->assertStandardPages($inactive);
        $this->assertSame('Approved story', $active->pages()->where('page_key', 'about')->value('body'));
        $this->assertSame('Merchant privacy text', $active->pages()->where('page_key', 'privacy')->value('body'));
        $this->assertSame('Merchant terms text', $active->pages()->where('page_key', 'terms')->value('body'));
        $this->assertSame('Merchant policy notes', $active->pages()->where('page_key', 'policies')->value('body'));
    }

    public function test_deleting_a_shop_cascades_to_its_pages_when_shop_is_force_deleted(): void
    {
        $shop = $this->shopFixture();
        $shopId = $shop->getKey();

        $shop->delete();
        $this->assertSame(4, ShopPage::query()->where('shop_id', $shopId)->count());

        $shop->forceDelete();

        $this->assertSame(0, ShopPage::query()->where('shop_id', $shopId)->count());
    }

    private function assertStandardPages(Shop $shop, bool $expectDefaultContent = true): void
    {
        $pages = $shop->pages()->orderBy('page_key')->get();

        $this->assertCount(4, $pages);
        $this->assertSame(['about', 'policies', 'privacy', 'terms'], $pages->pluck('page_key')->all());
        $this->assertTrue($pages->every(fn (ShopPage $page): bool => $page->page_type === ShopPage::TYPE_STANDARD));
        $this->assertTrue($pages->every(fn (ShopPage $page): bool => $page->slug === $page->page_key));

        $this->assertTrue($pages->every(fn (ShopPage $page): bool => $page->status === ShopPage::STATUS_DRAFT));

        if ($expectDefaultContent) {
            $this->assertNull($pages->firstWhere('page_key', 'about')->body);
            $this->assertNull($pages->firstWhere('page_key', 'policies')->body);
            $this->assertStringContainsString('Information received', $pages->firstWhere('page_key', 'privacy')->body);
            $this->assertStringContainsString('Prices and orders', $pages->firstWhere('page_key', 'terms')->body);
        }
    }

    private function shopFixture(string $status = 'active'): Shop
    {
        [, $merchant, $category] = $this->fixture('merchant');

        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Page Shop',
            'slug' => 'page-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => $status,
        ]);
    }

    private function fixture(string $role): array
    {
        $user = User::query()->create([
            'name' => 'CMS User',
            'email' => Str::random(12).'@example.test',
            'password' => 'password',
        ]);
        $roleId = DB::table('auth_roles')->where('slug', $role)->value('id') ?? DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $role,
            'slug' => $role,
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
            'business_name' => 'CMS Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'name' => 'CMS Category',
            'slug' => 'cms-category-'.Str::random(8),
            'status' => 'active',
        ]);

        return [$user, $merchant, $category];
    }
}
