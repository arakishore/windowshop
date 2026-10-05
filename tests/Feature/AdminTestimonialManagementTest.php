<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminTestimonialManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_guests_and_non_admins_cannot_manage_testimonials(): void
    {
        $this->get(route('admin.testimonials.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.testimonials.create'))->assertRedirect(route('admin.login'));

        $customer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Customer',
            'email' => 'customer-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->actingAs($customer)->get(route('admin.testimonials.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.testimonials.store'), [])->assertForbidden();
    }

    public function test_admin_can_view_testimonial_pages(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get(route('admin.testimonials.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.testimonials.create'))->assertOk();
    }

    public function test_admin_can_create_customer_testimonial_with_rating_and_photo(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'type' => 'customer',
            'name' => 'Priya Sharma',
            'body' => 'WindowShop helped me discover great local stores near my home.',
            'location' => 'Nashik',
            'rating' => '5',
            'photo' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
            'sort_order' => '3',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.testimonials.index'));
        $testimonial = Testimonial::query()->firstOrFail();
        $this->assertSame('customer', $testimonial->type);
        $this->assertSame('Priya Sharma', $testimonial->name);
        $this->assertSame(5, $testimonial->rating);
        $this->assertSame('Nashik', $testimonial->location);
        $this->assertSame(3, $testimonial->sort_order);
        $this->assertSame('active', $testimonial->status);
        $this->assertNull($testimonial->business_name);
        $this->assertNotNull($testimonial->uuid);
        $this->assertNotNull($testimonial->photo_path);
        Storage::disk('public')->assertExists($testimonial->photo_path);
    }

    public function test_admin_can_create_merchant_testimonial_without_rating(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'type' => 'merchant',
            'name' => 'Ramesh Patil',
            'body' => 'WindowShop brought new customers to my shop within weeks.',
            'location' => 'Nashik',
            'business_name' => 'Patil General Stores',
            'designation' => 'Owner',
            'sort_order' => '1',
            'status' => 'active',
        ])->assertRedirect(route('admin.testimonials.index'));

        $testimonial = Testimonial::query()->firstOrFail();
        $this->assertSame('merchant', $testimonial->type);
        $this->assertNull($testimonial->rating);
        $this->assertSame('Patil General Stores', $testimonial->business_name);
        $this->assertSame('Owner', $testimonial->designation);
        $this->assertNull($testimonial->photo_path);
    }

    public function test_admin_can_update_testimonial_replace_and_remove_photo(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $testimonial = $this->testimonial(['name' => 'Old Name']);

        $this->actingAs($admin)->put(route('admin.testimonials.update', $testimonial), [
            'type' => 'merchant',
            'name' => 'New Name',
            'body' => 'Updated testimonial body.',
            'location' => 'Pune',
            'business_name' => 'New Business',
            'designation' => 'Founder',
            'photo' => UploadedFile::fake()->image('new.jpg', 400, 400),
            'sort_order' => '7',
            'status' => 'inactive',
        ])->assertRedirect(route('admin.testimonials.edit', $testimonial));

        $testimonial->refresh();
        $this->assertSame('merchant', $testimonial->type);
        $this->assertSame('New Name', $testimonial->name);
        $this->assertSame('inactive', $testimonial->status);
        $this->assertNull($testimonial->rating);
        $this->assertNotNull($testimonial->photo_path);
        $photoPath = $testimonial->photo_path;
        Storage::disk('public')->assertExists($photoPath);

        $this->actingAs($admin)->put(route('admin.testimonials.update', $testimonial), [
            'type' => 'merchant',
            'name' => 'New Name',
            'body' => 'Updated testimonial body.',
            'remove_photo' => '1',
            'sort_order' => '7',
            'status' => 'inactive',
        ])->assertRedirect(route('admin.testimonials.edit', $testimonial));

        $testimonial->refresh();
        $this->assertNull($testimonial->photo_path);
        Storage::disk('public')->assertMissing($photoPath);
        Storage::disk('public')->assertMissing(dirname($photoPath));
    }

    public function test_testimonial_validation_rejects_invalid_input(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'type' => 'fan',
            'name' => 'X',
            'body' => 'Nice.',
            'rating' => '6',
            'sort_order' => '-1',
            'status' => 'published',
        ])->assertSessionHasErrors(['type', 'rating', 'sort_order', 'status']);

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'type' => 'customer',
            'name' => 'X',
            'body' => str_repeat('a', 2001),
            'status' => 'active',
        ])->assertSessionHasErrors(['body']);

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'type' => 'customer',
            'name' => 'X',
            'body' => 'Nice.',
            'photo' => UploadedFile::fake()->create('avatar.txt', 10, 'text/plain'),
            'status' => 'active',
        ])->assertSessionHasErrors(['photo']);

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_admin_can_deactivate_and_soft_delete_testimonial(): void
    {
        $admin = $this->adminUser();
        $testimonial = $this->testimonial();

        $this->actingAs($admin)->put(route('admin.testimonials.update', $testimonial), [
            'type' => 'customer',
            'name' => $testimonial->name,
            'body' => $testimonial->body,
            'status' => 'inactive',
        ])->assertRedirect();

        $this->assertSame('inactive', $testimonial->fresh()->status);

        $this->actingAs($admin)
            ->delete(route('admin.testimonials.destroy', $testimonial))
            ->assertRedirect(route('admin.testimonials.index'));

        $this->assertSoftDeleted('testimonials', ['id' => $testimonial->getKey()]);
        $this->assertNotNull($testimonial->fresh()->deleted_by);
    }

    public function test_admin_list_supports_search_type_and_status_filters_and_sorting(): void
    {
        $admin = $this->adminUser();
        $this->testimonial(['type' => 'customer', 'name' => 'Anita Desai', 'sort_order' => 5, 'status' => 'active']);
        $this->testimonial(['type' => 'merchant', 'name' => 'Vijay Traders Owner', 'business_name' => 'Vijay Traders', 'sort_order' => 1, 'status' => 'active']);
        $this->testimonial(['type' => 'customer', 'name' => 'Inactive Person', 'sort_order' => 0, 'status' => 'inactive']);

        $response = $this->actingAs($admin)->get(route('admin.testimonials.index', ['type' => 'merchant']));
        $response->assertOk()->assertSee('Vijay Traders')->assertDontSee('Anita Desai');

        $response = $this->actingAs($admin)->get(route('admin.testimonials.index', ['status' => 'inactive']));
        $response->assertOk()->assertSee('Inactive Person')->assertDontSee('Anita Desai');

        $response = $this->actingAs($admin)->get(route('admin.testimonials.index', ['search' => 'Anita']));
        $response->assertOk()->assertSee('Anita Desai')->assertDontSee('Vijay Traders');

        $response = $this->actingAs($admin)->get(route('admin.testimonials.index'));
        $positions = [
            strpos($response->getContent(), 'Inactive Person'),
            strpos($response->getContent(), 'Vijay Traders Owner'),
            strpos($response->getContent(), 'Anita Desai'),
        ];
        $this->assertTrue($positions[0] < $positions[1] && $positions[1] < $positions[2]);
    }

    public function test_homepage_displays_active_customer_testimonials_only(): void
    {
        $this->testimonial(['type' => 'customer', 'name' => 'Active Customer', 'body' => 'Loved shopping here daily.', 'rating' => 5, 'location' => 'Nashik', 'sort_order' => 0]);
        $this->testimonial(['type' => 'customer', 'name' => 'Hidden Customer', 'body' => 'You should not see this.', 'sort_order' => 1, 'status' => 'inactive']);
        $this->testimonial(['type' => 'merchant', 'name' => 'Shop Owner', 'body' => 'Merchant words stay away.', 'business_name' => 'Some Shop', 'sort_order' => 0]);

        $response = $this->get(route('storefront.home'))->assertOk();

        $response->assertSee('Customer Say!');
        $response->assertSee('Active Customer');
        $response->assertSee('Loved shopping here daily.');
        $response->assertSee('Rated 5 out of 5', false);
        $response->assertDontSee('You should not see this.');
        $response->assertDontSee('Merchant words stay away.');
    }

    public function test_homepage_respects_ordering_and_limit_and_hides_when_empty(): void
    {
        $this->get(route('storefront.home'))->assertOk()->assertDontSee('Customer Say!');

        for ($index = 1; $index <= 7; $index++) {
            $this->testimonial(['type' => 'customer', 'name' => 'Customer '.$index, 'body' => 'Body '.$index, 'sort_order' => $index]);
        }

        $response = $this->get(route('storefront.home'))->assertOk();

        $response->assertSee('Customer Say!');
        $response->assertSee('Customer 1');
        $response->assertSee('Customer 6');
        $response->assertDontSee('Customer 7');

        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Customer 1') < strpos($content, 'Customer 2'));
    }

    public function test_merchant_registration_displays_active_merchant_testimonials_only(): void
    {
        $this->testimonial(['type' => 'merchant', 'name' => 'Active Merchant', 'body' => 'Great platform for sellers.', 'business_name' => 'Best Shop', 'designation' => 'Owner', 'location' => 'Nashik', 'sort_order' => 0]);
        $this->testimonial(['type' => 'merchant', 'name' => 'Hidden Merchant', 'body' => 'Invisible merchant words.', 'sort_order' => 1, 'status' => 'inactive']);
        $this->testimonial(['type' => 'customer', 'name' => 'Shopping Customer', 'body' => 'Customer words stay away.', 'sort_order' => 0]);

        $response = $this->get(route('storefront.merchant-register'))->assertOk();

        $response->assertSee('Merchant Say!');
        $response->assertSee('Active Merchant');
        $response->assertSee('Best Shop');
        $response->assertSee('Owner');
        $response->assertDontSee('Invisible merchant words.');
        $response->assertDontSee('Customer words stay away.');
    }

    public function test_merchant_registration_respects_limit_and_hides_when_empty(): void
    {
        $this->get(route('storefront.merchant-register'))->assertOk()->assertDontSee('Merchant Say!');

        for ($index = 1; $index <= 5; $index++) {
            $this->testimonial(['type' => 'merchant', 'name' => 'Merchant '.$index, 'body' => 'Merchant body '.$index, 'sort_order' => $index]);
        }

        $response = $this->get(route('storefront.merchant-register'))->assertOk();

        $response->assertSee('Merchant Say!');
        $response->assertSee('Merchant 1');
        $response->assertSee('Merchant 4');
        $response->assertDontSee('Merchant 5');
    }

    private function testimonial(array $overrides = []): Testimonial
    {
        return Testimonial::query()->create(array_merge([
            'type' => 'customer',
            'name' => 'Test Person',
            'body' => 'A genuine testimonial body.',
            'location' => 'Nashik',
            'sort_order' => 0,
            'status' => 'active',
        ], $overrides));
    }

    private function adminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Testimonial Admin',
            'email' => 'testimonial-admin-'.Str::random(6).'@example.test',
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
