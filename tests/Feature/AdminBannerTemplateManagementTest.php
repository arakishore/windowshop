<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\Banner;
use App\Models\BannerTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminBannerTemplateManagementTest extends TestCase
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

    public function test_admin_can_view_banner_template_datatable_with_filters(): void
    {
        $admin = $this->userWithRole('admin');
        $general = $this->template([
            'code' => 'general_sale',
            'name' => 'General Sale',
            'category' => BannerTemplateCategory::GENERAL->value,
        ]);
        $this->template([
            'code' => 'festival_sale',
            'name' => 'Festival Sale',
            'category' => BannerTemplateCategory::FESTIVAL->value,
        ]);
        $this->banner(['banner_template_id' => $general->getKey()]);

        $this->actingAs($admin)
            ->get(route('admin.banner-templates.index', [
                'search' => 'general',
                'category' => BannerTemplateCategory::GENERAL->value,
            ]))
            ->assertOk()
            ->assertSee('Preview')
            ->assertSee('Available For')
            ->assertSee('Used By')
            ->assertSee('General Sale')
            ->assertSee('general_sale')
            ->assertSee('Store Hero')
            ->assertSee('1')
            ->assertDontSee('Festival Sale');
    }

    public function test_admin_can_create_banner_template_with_uploaded_images(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.banner-templates.store'), [
                'code' => 'general_store_hero',
                'category' => BannerTemplateCategory::GENERAL->value,
                'name' => 'General Store Hero',
                'description' => 'Reusable store hero template.',
                'default_title' => 'New Arrivals',
                'default_subtitle' => 'Fresh arrivals added every week',
                'default_button_text' => 'Shop Now',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1600, 500),
                'mobile_image' => UploadedFile::fake()->image('mobile.webp', 800, 900),
                'default_position' => BannerPosition::STORE_HERO->value,
                'availability' => BannerTemplateAvailability::BOTH->value,
                'event_code' => null,
                'start_offset_days' => -10,
                'end_offset_days' => 2,
                'sort_order' => 5,
                'status' => BannerTemplate::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $template = BannerTemplate::query()->where('code', 'general_store_hero')->firstOrFail();

        $this->assertSame($admin->getKey(), $template->created_by);
        Storage::disk('public')->assertExists($template->desktop_image_path);
        Storage::disk('public')->assertExists($template->mobile_image_path);
        $this->assertDatabaseHas('banner_templates', [
            'code' => 'general_store_hero',
            'default_position' => BannerPosition::STORE_HERO->value,
            'availability' => BannerTemplateAvailability::BOTH->value,
            'start_offset_days' => -10,
            'end_offset_days' => 2,
        ]);
    }

    public function test_admin_can_edit_template_and_replace_images(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $template = $this->template([
            'desktop_image_path' => 'banner-templates/existing/desktop.jpg',
            'mobile_image_path' => 'banner-templates/existing/mobile.jpg',
        ]);
        Storage::disk('public')->put($template->desktop_image_path, 'old desktop');
        Storage::disk('public')->put($template->mobile_image_path, 'old mobile');

        $this->actingAs($admin)
            ->put(route('admin.banner-templates.update', $template), [
                'code' => $template->code,
                'category' => BannerTemplateCategory::FASHION->value,
                'name' => 'Updated Template',
                'description' => 'Updated description',
                'default_title' => 'Best Sellers',
                'default_subtitle' => 'Handpicked collections just for you',
                'default_button_text' => 'Explore',
                'desktop_image' => UploadedFile::fake()->image('desktop.png', 1600, 500),
                'remove_mobile_image' => '1',
                'default_position' => BannerPosition::HOMEPAGE_HERO->value,
                'availability' => BannerTemplateAvailability::ADMIN->value,
                'sort_order' => 9,
                'status' => BannerTemplate::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('admin.banner-templates.edit', $template));

        $template->refresh();

        $this->assertSame('Updated Template', $template->name);
        $this->assertNull($template->mobile_image_path);
        Storage::disk('public')->assertMissing('banner-templates/existing/desktop.jpg');
        Storage::disk('public')->assertMissing('banner-templates/existing/mobile.jpg');
        Storage::disk('public')->assertExists($template->desktop_image_path);
    }

    public function test_admin_can_activate_and_deactivate_template(): void
    {
        $admin = $this->userWithRole('admin');
        $template = $this->template(['status' => BannerTemplate::STATUS_ACTIVE]);

        $this->actingAs($admin)
            ->patch(route('admin.banner-templates.toggle-status', $template))
            ->assertRedirect(route('admin.banner-templates.index'));

        $this->assertSame(BannerTemplate::STATUS_INACTIVE, $template->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.banner-templates.toggle-status', $template))
            ->assertRedirect(route('admin.banner-templates.index'));

        $this->assertSame(BannerTemplate::STATUS_ACTIVE, $template->fresh()->status);
    }

    public function test_template_availability_must_match_default_position_scope(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.banner-templates.store'), [
                'code' => 'bad_scope',
                'category' => BannerTemplateCategory::GENERAL->value,
                'name' => 'Bad Scope',
                'default_title' => 'Bad Scope',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1600, 500),
                'default_position' => BannerPosition::STORE_HERO->value,
                'availability' => BannerTemplateAvailability::ADMIN->value,
                'sort_order' => 0,
                'status' => BannerTemplate::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('availability');
    }

    private function template(array $overrides = []): BannerTemplate
    {
        return BannerTemplate::query()->create([
            'code' => $overrides['code'] ?? 'template_'.strtolower(Str::random(8)),
            'category' => $overrides['category'] ?? BannerTemplateCategory::GENERAL->value,
            'name' => $overrides['name'] ?? 'Template',
            'description' => $overrides['description'] ?? 'Reusable banner template.',
            'default_title' => $overrides['default_title'] ?? 'New Arrivals',
            'default_subtitle' => $overrides['default_subtitle'] ?? 'Fresh arrivals added every week',
            'default_button_text' => $overrides['default_button_text'] ?? 'Shop Now',
            'desktop_image_path' => $overrides['desktop_image_path'] ?? 'banner-templates/general/desktop.jpg',
            'mobile_image_path' => $overrides['mobile_image_path'] ?? 'banner-templates/general/mobile.jpg',
            'default_position' => $overrides['default_position'] ?? BannerPosition::STORE_HERO->value,
            'availability' => $overrides['availability'] ?? BannerTemplateAvailability::BOTH->value,
            'event_code' => $overrides['event_code'] ?? null,
            'start_offset_days' => $overrides['start_offset_days'] ?? null,
            'end_offset_days' => $overrides['end_offset_days'] ?? null,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'status' => $overrides['status'] ?? BannerTemplate::STATUS_ACTIVE,
        ]);
    }

    private function banner(array $overrides = []): Banner
    {
        return Banner::query()->create([
            'source_type' => 'template',
            'banner_template_id' => $overrides['banner_template_id'] ?? null,
            'position' => BannerPosition::STORE_HERO->value,
            'title' => 'Template Banner',
            'desktop_image_path' => 'banners/test/desktop.jpg',
            'link_type' => 'none',
            'sort_order' => 0,
            'status' => Banner::STATUS_ACTIVE,
        ]);
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::query()->create([
            'name' => Str::headline($roleSlug).' User',
            'email' => $roleSlug.'-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
        ]);

        $roleId = DB::table('auth_roles')->where('slug', $roleSlug)->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'name' => Str::headline($roleSlug),
                'slug' => $roleSlug,
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
