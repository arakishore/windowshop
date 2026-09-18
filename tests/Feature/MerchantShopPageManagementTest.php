<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ShopPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantShopPageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_list_shows_standard_pages_and_active_shop_and_sidebar_link(): void
    {
        [$user, $shop] = $this->fixture();

        $this->asShop($user, $shop)->get(route('merchant.shop-pages.index'))
            ->assertOk()
            ->assertSee('Shop Pages for '.$shop->name)
            ->assertSee('About Us')
            ->assertSee('Privacy Policy')
            ->assertSee('Terms &amp; Conditions', false)
            ->assertSee('Shop Policies')
            ->assertSee('Add New Page')
            ->assertSee('Standard');
    }

    public function test_privacy_and_terms_defaults_are_visible_in_editors(): void
    {
        [$user, $shop] = $this->fixture();

        $createResponse = $this->asShop($user, $shop)->get(route('merchant.shop-pages.create'))->assertOk();
        $this->assertSame(1, substr_count($createResponse->getContent(), 'ckeditor5.umd.js'));

        foreach (['privacy' => 'Information received', 'terms' => 'Prices and orders'] as $key => $text) {
            $page = $this->page($shop, $key);
            $response = $this->asShop($user, $shop)->get(route('merchant.shop-pages.edit', $page))
                ->assertOk()
                ->assertSee($text);
            $this->assertSame(1, substr_count($response->getContent(), 'ckeditor5.umd.js'));
        }
    }

    public function test_merchant_can_edit_each_standard_page_without_changing_its_identity(): void
    {
        [$user, $shop] = $this->fixture();

        foreach (['about', 'privacy', 'terms', 'policies'] as $key) {
            $page = $this->page($shop, $key);
            $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $page), [
                'body' => 'Merchant content for '.$key,
                'status' => 'draft',
            ])->assertRedirect(route('merchant.shop-pages.edit', $page));

            $page->refresh();
            $this->assertSame('Merchant content for '.$key, $page->body);
            $this->assertSame($key, $page->slug);
            $this->assertSame($key, $page->page_key);
            $this->assertSame(ShopPage::TYPE_STANDARD, $page->page_type);
        }

        $this->asShop($user, $shop)->get(route('merchant.shop-pages.edit', $this->page($shop, 'policies')))
            ->assertSee('Additional Policy Notes')
            ->assertSee(route('merchant.settings.edit'));
    }

    public function test_publishing_requires_content_and_unpublishing_clears_timestamp(): void
    {
        [$user, $shop] = $this->fixture();
        $page = $this->page($shop, 'about');

        $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $page), [
            'body' => '',
            'status' => 'published',
        ])->assertSessionHasErrors('body');

        $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $page), [
            'body' => 'Our story',
            'status' => 'published',
        ])->assertRedirect();
        $this->assertSame('published', $page->fresh()->status);
        $publishedAt = $page->fresh()->published_at;
        $this->assertNotNull($publishedAt);

        $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $page), [
            'body' => 'Updated story',
            'status' => 'published',
        ])->assertRedirect();
        $this->assertEquals($publishedAt, $page->fresh()->published_at);

        $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $page), [
            'body' => 'Updated story',
            'status' => 'draft',
        ])->assertRedirect();
        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($page->fresh()->published_at);
    }

    public function test_custom_page_slug_is_generated_and_crud_works(): void
    {
        [$user, $shop] = $this->fixture();

        $this->asShop($user, $shop)->get(route('merchant.shop-pages.create'))->assertOk();
        $this->asShop($user, $shop)->post(route('merchant.shop-pages.store'), [
            'title' => 'Size Guide',
            'body' => 'Measure carefully.',
            'status' => 'published',
        ])->assertRedirect();

        $page = $shop->pages()->where('slug', 'size-guide')->firstOrFail();
        $this->assertSame(ShopPage::TYPE_CUSTOM, $page->page_type);
        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);

        $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $page), [
            'title' => 'Fit Guide',
            'slug' => 'FIT Guide',
            'body' => 'Use a tape measure.',
            'status' => 'draft',
        ])->assertRedirect();
        $this->assertSame('fit-guide', $page->fresh()->slug);
        $this->assertSame('Fit Guide', $page->fresh()->title);
        $this->assertNull($page->fresh()->published_at);

        $this->asShop($user, $shop)->delete(route('merchant.shop-pages.destroy', $page))->assertRedirect();
        $this->assertDatabaseMissing('shop_pages', ['id' => $page->getKey()]);
    }

    public function test_slug_uniqueness_is_scoped_to_shop_and_duplicates_get_validation_errors(): void
    {
        [$user, $shop] = $this->fixture();
        $secondShop = $this->makeShop($shop->merchant);
        $payload = ['title' => 'Size Guide', 'slug' => 'size-guide', 'body' => 'Sizes', 'status' => 'draft'];

        $this->asShop($user, $shop)->post(route('merchant.shop-pages.store'), $payload)->assertRedirect();
        $this->asShop($user, $shop)->post(route('merchant.shop-pages.store'), $payload)->assertSessionHasErrors('slug');
        $this->asShop($user, $secondShop)->post(route('merchant.shop-pages.store'), $payload)->assertRedirect();

        $this->assertSame(2, ShopPage::query()->where('slug', 'size-guide')->count());
        $this->asShop($user, $shop)->post(route('merchant.shop-pages.store'), [
            ...$payload,
            'slug' => 'about',
        ])->assertSessionHasErrors('slug');
    }

    public function test_standard_pages_cannot_be_deleted_or_retyped(): void
    {
        [$user, $shop] = $this->fixture();
        $page = $this->page($shop, 'about');

        $this->asShop($user, $shop)->delete(route('merchant.shop-pages.destroy', $page))->assertForbidden();
        $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $page), [
            'body' => 'Changed',
            'status' => 'draft',
            'page_type' => 'custom',
            'page_key' => 'other',
            'title' => 'Other',
            'slug' => 'other',
        ])->assertSessionHasErrors(['page_type', 'page_key', 'title', 'slug']);

        $this->assertSame('about', $page->fresh()->page_key);
        $this->assertNull($page->fresh()->body);
    }

    public function test_crafted_standard_page_creation_is_rejected_and_rich_text_is_allowed(): void
    {
        [$user, $shop] = $this->fixture();

        $this->asShop($user, $shop)->post(route('merchant.shop-pages.store'), [
            'title' => 'Second About',
            'slug' => 'second-about',
            'body' => 'Content',
            'status' => 'draft',
            'page_type' => 'standard',
            'page_key' => 'about',
        ])->assertSessionHasErrors(['page_type', 'page_key']);

        $this->asShop($user, $shop)->post(route('merchant.shop-pages.store'), [
            'title' => 'Rich Text Page',
            'body' => '<h2>Size Guide</h2><p><strong>Measure carefully.</strong></p>',
            'status' => 'draft',
        ])->assertRedirect();

        $this->asShop($user, $shop)->post(route('merchant.shop-pages.store'), [
            'title' => 'Unsafe Page',
            'body' => '<script>alert(1)</script>',
            'status' => 'draft',
        ])->assertSessionHasErrors('body');
        $this->assertSame(5, $shop->pages()->count());
    }

    public function test_page_requests_are_scoped_to_the_current_shop_even_for_the_same_merchant(): void
    {
        [$user, $shop] = $this->fixture();
        $secondShop = $this->makeShop($shop->merchant);
        $page = $this->page($shop, 'about');

        $this->asShop($user, $secondShop)->get(route('merchant.shop-pages.edit', $page))->assertNotFound();
        $this->asShop($user, $secondShop)->put(route('merchant.shop-pages.update', $page), [
            'body' => 'Wrong shop', 'status' => 'draft',
        ])->assertForbidden();
        $this->assertNull($page->fresh()->body);
    }

    public function test_other_merchants_pages_cannot_be_read_modified_previewed_or_deleted(): void
    {
        [$user, $shop] = $this->fixture();
        [, $otherShop] = $this->fixture();
        $otherPage = $this->page($otherShop, 'privacy');

        $this->asShop($user, $shop)->get(route('merchant.shop-pages.edit', $otherPage))->assertNotFound();
        $this->asShop($user, $shop)->get(route('merchant.shop-pages.preview', $otherPage))->assertNotFound();
        $this->asShop($user, $shop)->put(route('merchant.shop-pages.update', $otherPage), [
            'body' => 'Intrusion', 'status' => 'draft',
        ])->assertForbidden();
        $this->asShop($user, $shop)->delete(route('merchant.shop-pages.destroy', $otherPage))->assertNotFound();
        $this->assertNotSame('Intrusion', $otherPage->fresh()->body);
    }

    public function test_preview_escapes_saved_plain_text(): void
    {
        [$user, $shop] = $this->fixture();
        $page = $this->page($shop, 'about');
        $page->forceFill(['body' => '<script>alert(1)</script>'])->save();

        $this->asShop($user, $shop)->get(route('merchant.shop-pages.preview', $page))
            ->assertOk()
            ->assertDontSee('alert(1)')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_preview_renders_saved_rich_text(): void
    {
        [$user, $shop] = $this->fixture();
        $page = $this->page($shop, 'about');
        $page->forceFill(['body' => '<h2>About us</h2><p><strong>Local shop</strong></p>'])->save();

        $this->asShop($user, $shop)->get(route('merchant.shop-pages.preview', $page))
            ->assertOk()
            ->assertSee('<h2>About us</h2>', false)
            ->assertSee('<strong>Local shop</strong>', false);
    }

    private function asShop(User $user, Shop $shop): static
    {
        return $this->actingAs($user)->withSession(['active_shop_id' => $shop->getKey()]);
    }

    private function page(Shop $shop, string $key): ShopPage
    {
        return $shop->pages()->where('page_key', $key)->firstOrFail();
    }

    private function fixture(): array
    {
        $user = User::query()->create([
            'name' => 'Page Merchant',
            'email' => Str::random(12).'@example.test',
            'password' => 'password',
        ]);
        $roleId = DB::table('auth_roles')->where('slug', 'merchant')->value('id') ?? DB::table('auth_roles')->insertGetId([
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
            'business_name' => 'Page Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        return [$user, $this->makeShop($merchant)];
    }

    private function makeShop(MerchantProfile $merchant): Shop
    {
        $category = ProductCategory::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel-'.Str::random(8),
            'status' => 'active',
        ]);

        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Page Shop '.Str::random(4),
            'slug' => 'page-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
    }
}
