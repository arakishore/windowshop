<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\User;
use App\Services\Cms\CmsPageInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminCmsPageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_standard_pages_are_initialized_as_drafts_from_existing_marketplace_copy(): void
    {
        $pages = CmsPage::query()->orderBy('page_key')->get();

        $this->assertSame(['privacy', 'return_refund', 'shipping', 'terms'], $pages->pluck('page_key')->all());
        $this->assertTrue($pages->every(fn (CmsPage $page) => $page->page_type === CmsPage::TYPE_STANDARD && $page->status === CmsPage::STATUS_DRAFT && $page->published_at === null));
        $this->assertStringContainsString('We do not sell personal information.', $pages->firstWhere('page_key', 'privacy')->body);
        $this->assertStringContainsString('Some merchants may offer store pickup.', $pages->firstWhere('page_key', 'shipping')->body);
        $this->assertStringNotContainsString('{{marketplace_name}}', $pages->pluck('body')->implode(' '));
        $this->assertSame(0, DB::table('shop_pages')->count());
    }

    public function test_initializer_backfills_missing_rows_without_overwriting_edits(): void
    {
        $terms = CmsPage::query()->where('page_key', 'terms')->firstOrFail();
        $terms->update(['body' => '<p>Reviewed text</p>', 'status' => CmsPage::STATUS_PUBLISHED, 'published_at' => now()]);
        CmsPage::query()->where('page_key', 'privacy')->delete();

        app(CmsPageInitializer::class)->initialize();
        app(CmsPageInitializer::class)->initialize();

        $this->assertSame(4, CmsPage::query()->where('page_type', CmsPage::TYPE_STANDARD)->count());
        $this->assertSame('<p>Reviewed text</p>', $terms->fresh()->body);
        $this->assertSame(CmsPage::STATUS_PUBLISHED, $terms->fresh()->status);
        $this->assertStringContainsString('Information We Collect', CmsPage::query()->where('page_key', 'privacy')->value('body'));
    }

    public function test_admin_can_manage_standard_page_without_changing_its_identity(): void
    {
        $this->actingAs($this->user('admin'));
        $page = CmsPage::query()->where('page_key', 'privacy')->firstOrFail();

        $response = $this->get(route('admin.cms-pages.edit', $page))->assertOk()->assertSee('We do not sell personal information.');
        $this->assertSame(1, substr_count($response->getContent(), 'ckeditor5.umd.js'));

        $this->put(route('admin.cms-pages.update', $page), [
            'body' => '<h2>Privacy</h2><p><strong>Reviewed</strong> text.</p>',
            'status' => 'published',
        ])->assertRedirect(route('admin.cms-pages.edit', $page));
        $this->assertSame(CmsPage::STATUS_PUBLISHED, $page->fresh()->status);
        $this->assertNotNull($page->fresh()->published_at);
        $publishedAt = $page->fresh()->published_at;

        $this->get(route('admin.cms-pages.preview', $page))->assertOk()
            ->assertSee('<h2>Privacy</h2>', false)
            ->assertSee('<strong>Reviewed</strong>', false)
            ->assertSee('Published');

        $this->put(route('admin.cms-pages.update', $page), [
            'body' => '<p>Still published</p>', 'status' => 'published',
        ])->assertRedirect();
        $this->assertEquals($publishedAt, $page->fresh()->published_at);

        $this->put(route('admin.cms-pages.update', $page), [
            'body' => '<p>Draft again</p>', 'status' => 'draft',
        ])->assertRedirect();
        $this->assertNull($page->fresh()->published_at);
        $this->get(route('admin.cms-pages.preview', $page))->assertOk()->assertSee('Draft');
        $this->assertSame('privacy', $page->fresh()->page_key);
        $this->assertSame('privacy-policy', $page->fresh()->slug);
    }

    public function test_standard_page_identity_is_protected_and_cannot_be_deleted(): void
    {
        $this->actingAs($this->user('super_admin'));
        $page = CmsPage::query()->where('page_key', 'terms')->firstOrFail();

        $this->put(route('admin.cms-pages.update', $page), [
            'body' => 'Changed', 'status' => 'draft', 'title' => 'Changed',
            'slug' => 'changed', 'page_key' => 'about', 'page_type' => 'custom',
        ])->assertSessionHasErrors(['title', 'slug', 'page_key', 'page_type']);
        $this->delete(route('admin.cms-pages.destroy', $page))->assertForbidden();
        $this->assertSame('terms', $page->fresh()->page_key);
        $this->assertSame(CmsPage::TYPE_STANDARD, $page->fresh()->page_type);
    }

    public function test_custom_pages_support_slug_normalization_uniqueness_edit_and_delete(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('admin.cms-pages.index'))->assertOk()->assertSee('Marketplace Pages')->assertSee('Content Management');
        $create = $this->get(route('admin.cms-pages.create'))->assertOk();
        $this->assertSame(1, substr_count($create->getContent(), 'ckeditor5.umd.js'));

        $this->post(route('admin.cms-pages.store'), [
            'title' => 'Seller Guidelines', 'body' => '<p>Guidance</p>', 'status' => 'draft',
        ])->assertRedirect();
        $page = CmsPage::query()->where('slug', 'seller-guidelines')->firstOrFail();
        $this->assertSame(CmsPage::TYPE_CUSTOM, $page->page_type);
        $this->assertNull($page->page_key);

        $this->post(route('admin.cms-pages.store'), [
            'title' => 'Again', 'slug' => 'Seller Guidelines', 'body' => 'Text', 'status' => 'draft',
        ])->assertSessionHasErrors('slug');
        $this->post(route('admin.cms-pages.store'), [
            'title' => 'Fake standard', 'slug' => 'privacy-policy', 'status' => 'draft',
        ])->assertSessionHasErrors('slug');

        $this->put(route('admin.cms-pages.update', $page), [
            'title' => 'Buyer Guide', 'slug' => 'Buyer Guide', 'body' => '<p>Updated</p>', 'status' => 'published',
        ])->assertRedirect();
        $this->assertSame('buyer-guide', $page->fresh()->slug);
        $this->assertSame(CmsPage::STATUS_PUBLISHED, $page->fresh()->status);
        $this->delete(route('admin.cms-pages.destroy', $page))->assertRedirect(route('admin.cms-pages.index'));
        $this->assertDatabaseMissing('cms_pages', ['id' => $page->getKey()]);
    }

    public function test_admin_can_search_and_filter_pages(): void
    {
        $this->actingAs($this->user('admin'));
        CmsPage::query()->create([
            'page_type' => CmsPage::TYPE_CUSTOM, 'title' => 'Buyer Guide', 'slug' => 'buyer-guide',
            'body' => '<p>Guide</p>', 'status' => CmsPage::STATUS_PUBLISHED, 'published_at' => now(),
        ]);

        $this->get(route('admin.cms-pages.index', ['q' => 'buyer', 'type' => 'custom', 'status' => 'published']))
            ->assertOk()->assertSee('Buyer Guide')->assertDontSee('Privacy Policy');
        $this->get(route('admin.cms-pages.index', ['q' => 'buyer-guide', 'status' => 'draft']))
            ->assertOk()->assertDontSee('Buyer Guide');
    }

    public function test_bulk_status_changes_preserve_publication_rules(): void
    {
        $this->actingAs($this->user('admin'));
        $page = CmsPage::query()->where('page_key', 'shipping')->firstOrFail();
        $url = route('admin.cms-pages.bulk-action');

        $this->post($url, ['action' => 'publish', 'page_ids' => [$page->id]])->assertRedirect(route('admin.cms-pages.index'));
        $publishedAt = $page->fresh()->published_at;
        $this->assertNotNull($publishedAt);
        $this->post($url, ['action' => 'publish', 'page_ids' => [$page->id]])->assertRedirect();
        $this->assertEquals($publishedAt, $page->fresh()->published_at);
        $this->post($url, ['action' => 'draft', 'page_ids' => [$page->id]])->assertRedirect();
        $this->assertSame(CmsPage::STATUS_DRAFT, $page->fresh()->status);
        $this->assertNull($page->fresh()->published_at);
    }

    public function test_bulk_action_rejects_blank_publication_and_standard_deletion_without_partial_changes(): void
    {
        $this->actingAs($this->user('admin'));
        $standard = CmsPage::query()->where('page_key', 'shipping')->firstOrFail();
        $custom = CmsPage::query()->create([
            'page_type' => CmsPage::TYPE_CUSTOM, 'title' => 'Empty', 'slug' => 'empty',
            'body' => '<script>alert(1)</script>', 'status' => CmsPage::STATUS_DRAFT,
        ]);
        $url = route('admin.cms-pages.bulk-action');

        $this->post($url, ['action' => 'publish', 'page_ids' => [$standard->id, $custom->id]])->assertSessionHasErrors('page_ids');
        $this->assertSame(CmsPage::STATUS_DRAFT, $standard->fresh()->status);
        $this->post($url, ['action' => 'delete', 'page_ids' => [$standard->id, $custom->id]])->assertSessionHasErrors('page_ids');
        $this->assertDatabaseHas('cms_pages', ['id' => $custom->id]);
        $this->post($url, ['action' => 'delete', 'page_ids' => [$custom->id]])->assertRedirect();
        $this->assertDatabaseMissing('cms_pages', ['id' => $custom->id]);
        $this->post($url, ['action' => 'draft', 'page_ids' => [$standard->id, $standard->id]])->assertSessionHasErrors('page_ids.1');
        $this->post($url, ['action' => 'publish', 'page_ids' => [999999]])->assertSessionHasErrors('page_ids.0');
    }

    public function test_unsafe_html_is_sanitized_and_empty_content_cannot_be_published(): void
    {
        $this->actingAs($this->user('admin'));
        $page = CmsPage::query()->where('page_key', 'privacy')->firstOrFail();

        $this->put(route('admin.cms-pages.update', $page), [
            'body' => '<script>alert(1)</script>', 'status' => 'published',
        ])->assertSessionHasErrors('body');
        $this->put(route('admin.cms-pages.update', $page), [
            'body' => '<p onclick="alert(1)"><em>Safe</em><a href="javascript:alert(2)">link</a><script>alert(3)</script></p>',
            'status' => 'published',
        ])->assertRedirect();

        $this->assertStringContainsString('<em>Safe</em>', $page->fresh()->body);
        $this->assertStringNotContainsString('onclick', $page->fresh()->body);
        $this->assertStringNotContainsString('javascript:', $page->fresh()->body);
        $this->assertStringNotContainsString('alert(3)', $page->fresh()->body);
        $this->get(route('admin.cms-pages.preview', $page))->assertOk()->assertDontSee('alert(3)');
    }

    public function test_merchant_cannot_access_admin_cms_and_public_routes_stay_unchanged(): void
    {
        $page = CmsPage::query()->where('page_key', 'privacy')->firstOrFail();
        $page->update(['body' => '<p>Admin draft only</p>', 'status' => CmsPage::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->actingAs($this->user('merchant'));
        $this->get(route('admin.cms-pages.index'))->assertForbidden();
        $this->get(route('admin.cms-pages.edit', $page))->assertForbidden();
        $this->post(route('admin.cms-pages.store'), ['title' => 'No'])->assertForbidden();
        $this->put(route('admin.cms-pages.update', $page), ['body' => 'No', 'status' => 'draft'])->assertForbidden();
        $this->delete(route('admin.cms-pages.destroy', $page))->assertForbidden();

        $this->get(route('storefront.privacy'))->assertOk()
            ->assertViewIs('storefront.pages.marketplace-cms-page')
            ->assertSee('Admin draft only');
        foreach (['storefront.about', 'storefront.terms', 'storefront.shipping', 'storefront.returns', 'storefront.contact', 'storefront.faq'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_marketplace_about_routes_always_render_the_static_designed_page(): void
    {
        CmsPage::query()->create([
            'page_type' => CmsPage::TYPE_STANDARD,
            'page_key' => 'about',
            'title' => 'About Us',
            'slug' => 'about-us',
            'body' => '<p>CMS About replacement must not render</p>',
            'status' => CmsPage::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        foreach (['/about', '/about-us'] as $url) {
            $this->get($url)->assertOk()
                ->assertViewIs('storefront.pages.about')
                ->assertSee('Who We Are')
                ->assertSee('Our Promise')
                ->assertSee('Good For Shops And Local Clients')
                ->assertDontSee('CMS About replacement must not render');
        }
    }

    public function test_published_marketplace_pages_render_cms_content_on_existing_footer_routes(): void
    {
        $pages = [
            'privacy' => 'storefront.privacy',
            'terms' => 'storefront.terms',
            'shipping' => 'storefront.shipping',
            'return_refund' => 'storefront.returns',
        ];

        foreach ($pages as $key => $route) {
            $page = CmsPage::query()->where('page_key', $key)->firstOrFail();
            $page->update([
                'body' => '<h2>CMS '.$key.'</h2><p onclick="bad()"><strong>Published content</strong></p><script>bad()</script>',
                'status' => CmsPage::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            $this->get(route($route))->assertOk()
                ->assertViewIs('storefront.pages.marketplace-cms-page')
                ->assertSee('CMS '.$key)
                ->assertSee('<strong>Published content</strong>', false)
                ->assertDontSee('onclick', false)
                ->assertDontSee('bad()', false);
        }
    }

    public function test_draft_and_future_marketplace_pages_keep_the_existing_static_fallbacks(): void
    {
        $privacy = CmsPage::query()->where('page_key', 'privacy')->firstOrFail();
        $privacy->update([
            'body' => '<p>Draft CMS privacy</p>',
            'status' => CmsPage::STATUS_DRAFT,
            'published_at' => null,
        ]);
        $this->get(route('storefront.privacy'))->assertOk()
            ->assertViewIs('storefront.pages.privacy')
            ->assertSee('Information We Collect')
            ->assertDontSee('Draft CMS privacy');

        $shipping = CmsPage::query()->where('page_key', 'shipping')->firstOrFail();
        $shipping->update([
            'body' => '<p>Future CMS shipping</p>',
            'status' => CmsPage::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]);
        $this->get(route('storefront.shipping'))->assertOk()
            ->assertViewIs('storefront.pages.shipping')
            ->assertSee('Shipping Methods')
            ->assertDontSee('Future CMS shipping');
    }

    public function test_existing_about_and_contact_records_are_retained_but_not_managed(): void
    {
        $this->actingAs($this->user('admin'));
        $about = CmsPage::query()->create([
            'page_type' => CmsPage::TYPE_STANDARD,
            'page_key' => 'about',
            'title' => 'About Us',
            'slug' => 'about-us',
            'body' => '<p>Retained draft</p>',
            'status' => CmsPage::STATUS_DRAFT,
        ]);
        $contact = CmsPage::query()->create([
            'page_type' => CmsPage::TYPE_STANDARD,
            'page_key' => 'contact',
            'title' => 'Contact Information',
            'slug' => 'contact-us',
            'body' => '<p>Retained draft</p>',
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $this->get(route('admin.cms-pages.index'))->assertOk()
            ->assertDontSee('About Us')
            ->assertDontSee('Contact Information');
        $this->get(route('admin.cms-pages.edit', $about))->assertNotFound();
        $this->get(route('admin.cms-pages.preview', $contact))->assertNotFound();
        $this->post(route('admin.cms-pages.bulk-action'), [
            'action' => 'publish',
            'page_ids' => [$about->id],
        ])->assertSessionHasErrors('page_ids');

        $this->assertDatabaseHas('cms_pages', ['id' => $about->id, 'status' => CmsPage::STATUS_DRAFT]);
        $this->assertDatabaseHas('cms_pages', ['id' => $contact->id, 'status' => CmsPage::STATUS_DRAFT]);
    }

    private function user(string $role): User
    {
        $user = User::query()->create([
            'name' => 'CMS '.ucfirst($role),
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

        return $user;
    }
}
