<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminFaqManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_guests_and_non_admins_cannot_manage_faqs(): void
    {
        $this->get(route('admin.faqs.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.faqs.create'))->assertRedirect(route('admin.login'));

        $customer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Customer',
            'email' => 'customer-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->actingAs($customer)->get(route('admin.faqs.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.faqs.store'), [])->assertForbidden();
    }

    public function test_admin_can_view_faq_pages(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get(route('admin.faqs.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.faqs.create'))->assertOk();
    }

    public function test_admin_can_create_faq_in_each_category(): void
    {
        $admin = $this->adminUser();

        foreach (['general', 'customer', 'order', 'merchant'] as $category) {
            $this->actingAs($admin)->post(route('admin.faqs.store'), [
                'category' => $category,
                'question' => 'Question for '.$category.'?',
                'answer' => 'Answer for '.$category.'.',
                'sort_order' => '2',
                'status' => 'active',
            ])->assertRedirect(route('admin.faqs.index'));
        }

        $this->assertSame(4, Faq::query()->count());

        foreach (['general', 'customer', 'order', 'merchant'] as $category) {
            $faq = Faq::query()->where('category', $category)->firstOrFail();
            $this->assertSame('Question for '.$category.'?', $faq->question);
            $this->assertSame(2, $faq->sort_order);
            $this->assertSame('active', $faq->status);
            $this->assertNotNull($faq->uuid);
        }
    }

    public function test_admin_can_update_faq(): void
    {
        $admin = $this->adminUser();
        $faq = $this->faq(['question' => 'Old question?']);

        $this->actingAs($admin)->put(route('admin.faqs.update', $faq), [
            'category' => 'order',
            'question' => 'New question?',
            'answer' => 'New answer.',
            'sort_order' => '9',
            'status' => 'inactive',
        ])->assertRedirect(route('admin.faqs.edit', $faq));

        $faq->refresh();
        $this->assertSame('order', $faq->category);
        $this->assertSame('New question?', $faq->question);
        $this->assertSame(9, $faq->sort_order);
        $this->assertSame('inactive', $faq->status);
    }

    public function test_admin_can_soft_delete_faq(): void
    {
        $admin = $this->adminUser();
        $faq = $this->faq();

        $this->actingAs($admin)
            ->delete(route('admin.faqs.destroy', $faq))
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertSoftDeleted('faqs', ['id' => $faq->getKey()]);
        $this->assertNotNull($faq->fresh()->deleted_by);
    }

    public function test_faq_validation_rejects_invalid_input(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.faqs.store'), [
            'category' => 'audience',
            'question' => 'Q?',
            'answer' => 'A.',
            'sort_order' => '-1',
            'status' => 'published',
        ])->assertSessionHasErrors(['category', 'sort_order', 'status']);

        $this->actingAs($admin)->post(route('admin.faqs.store'), [
            'category' => 'general',
            'question' => str_repeat('q', 256),
            'answer' => 'A.',
            'status' => 'active',
        ])->assertSessionHasErrors(['question']);

        $this->actingAs($admin)->post(route('admin.faqs.store'), [
            'category' => 'general',
            'question' => 'Q?',
            'answer' => str_repeat('a', 2001),
            'status' => 'active',
        ])->assertSessionHasErrors(['answer']);

        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_admin_list_supports_search_category_and_status_filters(): void
    {
        $admin = $this->adminUser();
        $this->faq(['category' => 'general', 'question' => 'What is WindowShop?', 'answer' => 'A local marketplace.', 'status' => 'active']);
        $this->faq(['category' => 'merchant', 'question' => 'How do I join?', 'answer' => 'Register as merchant.', 'status' => 'active']);
        $this->faq(['category' => 'order', 'question' => 'Hidden question?', 'answer' => 'Hidden answer.', 'status' => 'inactive']);

        $response = $this->actingAs($admin)->get(route('admin.faqs.index', ['category' => 'merchant']));
        $response->assertOk()->assertSee('How do I join?')->assertDontSee('What is WindowShop?');

        $response = $this->actingAs($admin)->get(route('admin.faqs.index', ['status' => 'inactive']));
        $response->assertOk()->assertSee('Hidden question?')->assertDontSee('What is WindowShop?');

        $response = $this->actingAs($admin)->get(route('admin.faqs.index', ['search' => 'marketplace']));
        $response->assertOk()->assertSee('What is WindowShop?')->assertDontSee('How do I join?');
    }

    public function test_public_faq_page_displays_only_active_faqs_in_category_order(): void
    {
        $this->faq(['category' => 'merchant', 'question' => 'Merchant question?', 'answer' => 'Merchant answer.', 'sort_order' => 0]);
        $this->faq(['category' => 'general', 'question' => 'General question?', 'answer' => 'General answer.', 'sort_order' => 0]);
        $this->faq(['category' => 'order', 'question' => 'Order question?', 'answer' => 'Order answer.', 'sort_order' => 0]);
        $this->faq(['category' => 'customer', 'question' => 'Customer question?', 'answer' => 'Customer answer.', 'sort_order' => 0]);
        $this->faq(['category' => 'general', 'question' => 'Hidden general?', 'answer' => 'Hidden.', 'status' => 'inactive']);
        $deleted = $this->faq(['category' => 'general', 'question' => 'Deleted general?', 'answer' => 'Deleted.']);
        $deleted->delete();

        $response = $this->get(route('storefront.faq'))->assertOk();

        $response->assertSee('General question?');
        $response->assertSee('Customer question?');
        $response->assertSee('Order question?');
        $response->assertSee('Merchant question?');
        $response->assertDontSee('Hidden general?');
        $response->assertDontSee('Deleted general?');

        $content = $response->getContent();
        $positions = [
            strpos($content, 'General question?'),
            strpos($content, 'Customer question?'),
            strpos($content, 'Order question?'),
            strpos($content, 'Merchant question?'),
        ];
        $this->assertTrue($positions[0] < $positions[1] && $positions[1] < $positions[2] && $positions[2] < $positions[3]);
    }

    public function test_public_faq_page_respects_sorting_and_hides_empty_categories(): void
    {
        $this->faq(['category' => 'order', 'question' => 'Second order?', 'answer' => 'B.', 'sort_order' => 5]);
        $this->faq(['category' => 'order', 'question' => 'First order?', 'answer' => 'A.', 'sort_order' => 1]);

        $response = $this->get(route('storefront.faq'))->assertOk();
        $content = $response->getContent();

        $this->assertTrue(strpos($content, 'First order?') < strpos($content, 'Second order?'));
        $response->assertDontSee('id="general"', false);
        $response->assertDontSee('id="customer"', false);
        $response->assertDontSee('id="merchant"', false);
        $response->assertSee('id="order"', false);
    }

    public function test_public_faq_page_shows_message_when_no_active_faqs(): void
    {
        $this->faq(['category' => 'general', 'question' => 'Hidden?', 'answer' => 'Hidden.', 'status' => 'inactive']);

        $response = $this->get(route('storefront.faq'))->assertOk();

        $response->assertSee('No FAQs are available at the moment. Please contact us if you need help.');
        $response->assertSee('Need More Help?');
        $response->assertSee('Contact Us');
        $response->assertDontSee('faq-general-');
    }

    public function test_public_faq_page_uses_unique_accessible_accordion_ids(): void
    {
        $first = $this->faq(['category' => 'general', 'question' => 'First?', 'answer' => 'First answer.', 'sort_order' => 0]);
        $second = $this->faq(['category' => 'general', 'question' => 'Second?', 'answer' => 'Second answer.', 'sort_order' => 1]);

        $response = $this->get(route('storefront.faq'))->assertOk();
        $content = $response->getContent();

        $firstId = 'faq-general-'.$first->getKey();
        $secondId = 'faq-general-'.$second->getKey();

        $this->assertStringContainsString('data-bs-target="#'.$firstId.'"', $content);
        $this->assertStringContainsString('aria-controls="'.$firstId.'"', $content);
        $this->assertStringContainsString('id="'.$firstId.'"', $content);
        $this->assertStringContainsString('data-bs-target="#'.$secondId.'"', $content);
        $this->assertSame(1, substr_count($content, 'id="'.$firstId.'"'));
        $this->assertStringContainsString('data-bs-parent="#general-faq"', $content);
        $this->assertStringContainsString('<button type="button" class="accordion-title', $content);
        $this->assertStringNotContainsString('<div class="accordion-title"', $content);
    }

    public function test_faq_answers_are_escaped(): void
    {
        $this->faq(['category' => 'general', 'question' => 'XSS?', 'answer' => '<script>alert(1)</script>']);

        $response = $this->get(route('storefront.faq'))->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    private function faq(array $overrides = []): Faq
    {
        return Faq::query()->create(array_merge([
            'category' => 'general',
            'question' => 'Sample question?',
            'answer' => 'Sample answer.',
            'sort_order' => 0,
            'status' => 'active',
        ], $overrides));
    }

    private function adminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'FAQ Admin',
            'email' => 'faq-admin-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleId = DB::table('auth_roles')->insertGetId([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
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
