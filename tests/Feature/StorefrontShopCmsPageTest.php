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

class StorefrontShopCmsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_published_standard_and_custom_pages_are_shop_scoped(): void
    {
        $shop = $this->shop();
        $other = $this->shop();
        $about = $shop->pages()->where('page_key', 'about')->firstOrFail();
        $this->publish($about, '<h2>Our beginnings</h2><p><strong>Welcome</strong> to our shop.</p>');
        $custom = $shop->pages()->create([
            'page_type' => ShopPage::TYPE_CUSTOM,
            'title' => 'Our Story',
            'slug' => 'our-story',
            'body' => '<p>Custom story</p>',
            'status' => ShopPage::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get($this->url($shop, 'about'))->assertOk()
            ->assertSee('<h2>Our beginnings</h2>', false)
            ->assertSee('<strong>Welcome</strong>', false)
            ->assertSee('<title>About Us | Page Shop | WindowShop</title>', false)
            ->assertSeeInOrder(['shop-profile-hero', 'shop-profile-nav', 'shop-cms-article'], false)
            ->assertSee(route('storefront.stores.show', $shop->slug).'#shop-products', false)
            ->assertSee(route('storefront.stores.show', $shop->slug).'#shop-location', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('Our Story');
        $this->get($this->url($shop, $custom->slug))->assertOk()
            ->assertSee('shop-profile-hero', false)
            ->assertSee('shop-profile-nav', false)
            ->assertSee('Custom story');
        $this->get($this->url($other, 'about'))->assertNotFound();
        $this->get($this->url($other, $custom->slug))->assertNotFound();
        $this->get($this->url($shop, 'missing'))->assertNotFound();
    }

    public function test_draft_future_and_inactive_content_are_not_public(): void
    {
        $shop = $this->shop();
        $draft = $shop->pages()->where('page_key', 'privacy')->firstOrFail();
        $this->get($this->url($shop, $draft->slug))->assertNotFound();

        $custom = $shop->pages()->create([
            'page_type' => ShopPage::TYPE_CUSTOM,
            'title' => 'Draft Story',
            'slug' => 'draft-story',
            'status' => ShopPage::STATUS_DRAFT,
        ]);
        $this->get($this->url($shop, $custom->slug))->assertNotFound();

        $about = $shop->pages()->where('page_key', 'about')->firstOrFail();
        $this->publish($about, 'Soon');
        $about->update(['published_at' => now()->addDay()]);
        $this->get($this->url($shop, 'about'))->assertNotFound();
        $about->update(['published_at' => now()]);
        $shop->update(['status' => 'inactive']);
        $this->get($this->url($shop, 'about'))->assertNotFound();
        $shop->update(['status' => 'active']);
        $shop->merchant->update(['status' => 'inactive']);
        $this->get($this->url($shop, 'about'))->assertNotFound();
    }

    public function test_shop_footer_links_only_published_merchant_pages(): void
    {
        $shop = $this->shop();
        foreach (['about', 'privacy', 'terms'] as $key) {
            $this->publish($shop->pages()->where('page_key', $key)->firstOrFail(), 'Published '.$key);
        }

        $response = $this->get(route('storefront.stores.show', $shop->slug))->assertOk();
        foreach (['about', 'privacy', 'terms'] as $key) {
            $response->assertSee($this->url($shop, $key), false);
            $this->get($this->url($shop, $key))->assertOk()->assertSee('Published '.$key);
        }
        $response->assertDontSee($this->url($shop, 'policies'), false);
        $response->assertSee(route('storefront.privacy'), false);
        $response->assertSee(route('storefront.terms'), false);
        $this->assertNotSame(route('storefront.privacy'), $this->url($shop, 'privacy'));
        $this->assertNotSame(route('storefront.terms'), $this->url($shop, 'terms'));
    }

    public function test_public_page_sanitizes_legacy_html_and_preserves_plain_text(): void
    {
        $shop = $this->shop();
        $privacy = $shop->pages()->where('page_key', 'privacy')->firstOrFail();
        $this->publish($privacy, "Introduction\nCustomer information");
        $this->get($this->url($shop, 'privacy'))->assertOk()->assertSee('Introduction<br', false);

        $this->publish($privacy, '<p onclick="alert(1)">Safe <em>text</em><script>alert(2)</script><a href="javascript:alert(3)">link</a></p>');
        $this->get($this->url($shop, 'privacy'))->assertOk()
            ->assertSee('<em>text</em>', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('javascript:', false)
            ->assertDontSee('alert(2)', false);
    }

    public function test_shop_policies_page_displays_only_saved_additional_notes(): void
    {
        $shop = $this->shop();
        $policies = $shop->pages()->where('page_key', 'policies')->firstOrFail();
        $this->publish($policies, '<p>Additional pickup notes.</p>');

        $this->get($this->url($shop, 'policies'))->assertOk()
            ->assertSee('Shop Policies')
            ->assertSee('<p>Additional pickup notes.</p>', false);
    }

    private function url(Shop $shop, string $slug): string
    {
        return route('storefront.stores.pages.show', [$shop->slug, $slug]);
    }

    private function publish(ShopPage $page, string $body): void
    {
        $page->update(['body' => $body, 'status' => ShopPage::STATUS_PUBLISHED, 'published_at' => now()]);
    }

    private function shop(): Shop
    {
        $user = User::query()->create([
            'name' => 'CMS User',
            'email' => Str::random(12).'@example.test',
            'password' => 'password',
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

        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Page Shop',
            'slug' => 'page-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
    }
}
