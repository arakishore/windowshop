<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Enums\BannerSourceType;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\Banner;
use App\Models\BannerTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class BannerTemplateFoundationTest extends TestCase
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

    public function test_banner_templates_table_can_store_valid_template_and_generate_uuid(): void
    {
        $template = $this->template([
            'code' => 'general_store_hero',
            'name' => 'General Store Hero',
        ]);

        $this->assertNotNull($template->uuid);
        $this->assertDatabaseHas('banner_templates', [
            'code' => 'general_store_hero',
            'category' => BannerTemplateCategory::GENERAL->value,
            'availability' => BannerTemplateAvailability::BOTH->value,
            'default_position' => BannerPosition::STORE_HERO->value,
        ]);
    }

    public function test_banner_template_soft_delete_works(): void
    {
        $template = $this->template();

        $template->delete();

        $this->assertSoftDeleted('banner_templates', [
            'id' => $template->getKey(),
        ]);
    }

    public function test_active_ordered_category_and_event_scopes_work(): void
    {
        $second = $this->template([
            'code' => 'fashion_second',
            'category' => BannerTemplateCategory::FASHION->value,
            'event_code' => 'diwali',
            'name' => 'Second',
            'sort_order' => 20,
        ]);
        $first = $this->template([
            'code' => 'fashion_first',
            'category' => BannerTemplateCategory::FASHION->value,
            'event_code' => 'diwali',
            'name' => 'First',
            'sort_order' => 10,
        ]);
        $this->template([
            'code' => 'inactive_fashion',
            'category' => BannerTemplateCategory::FASHION->value,
            'status' => BannerTemplate::STATUS_INACTIVE,
            'sort_order' => 1,
        ]);
        $this->template([
            'code' => 'grocery_first',
            'category' => BannerTemplateCategory::GROCERY->value,
            'sort_order' => 1,
        ]);

        $templates = BannerTemplate::query()
            ->active()
            ->forCategory(BannerTemplateCategory::FASHION)
            ->forEvent('diwali')
            ->ordered()
            ->get();

        $this->assertSame([$first->getKey(), $second->getKey()], $templates->pluck('id')->all());
    }

    public function test_availability_scopes_include_expected_records(): void
    {
        $admin = $this->template(['code' => 'admin_only', 'availability' => BannerTemplateAvailability::ADMIN->value]);
        $merchant = $this->template(['code' => 'merchant_only', 'availability' => BannerTemplateAvailability::MERCHANT->value]);
        $both = $this->template(['code' => 'both_available', 'availability' => BannerTemplateAvailability::BOTH->value]);

        $this->assertSame(
            [$admin->getKey(), $both->getKey()],
            BannerTemplate::query()->availableForAdmin()->ordered()->pluck('id')->all(),
        );
        $this->assertSame(
            [$merchant->getKey(), $both->getKey()],
            BannerTemplate::query()->availableForMerchant()->ordered()->pluck('id')->all(),
        );
    }

    public function test_banner_belongs_to_banner_template_and_source_helpers_work(): void
    {
        $template = $this->template();
        $templateBanner = $this->banner([
            'source_type' => BannerSourceType::TEMPLATE->value,
            'banner_template_id' => $template->getKey(),
        ]);
        $customBanner = $this->banner();

        $this->assertTrue($templateBanner->usesTemplate());
        $this->assertFalse($templateBanner->usesCustomUpload());
        $this->assertTrue($templateBanner->bannerTemplate->is($template));
        $this->assertFalse($customBanner->usesTemplate());
        $this->assertTrue($customBanner->usesCustomUpload());
    }

    public function test_existing_custom_banners_remain_compatible_with_default_source_type(): void
    {
        $banner = $this->banner([
            'source_type' => null,
        ]);

        $this->assertNull($banner->banner_template_id);
        $this->assertTrue($banner->usesCustomUpload());
    }

    public function test_force_deleting_template_nulls_banner_template_id_without_deleting_banner(): void
    {
        $template = $this->template();
        $banner = $this->banner([
            'source_type' => BannerSourceType::TEMPLATE->value,
            'banner_template_id' => $template->getKey(),
        ]);

        $template->forceDelete();
        $banner->refresh();

        $this->assertNull($banner->banner_template_id);
        $this->assertDatabaseHas('banners', [
            'id' => $banner->getKey(),
            'deleted_at' => null,
        ]);
    }

    private function template(array $overrides = []): BannerTemplate
    {
        return BannerTemplate::query()->create([
            'code' => $overrides['code'] ?? 'template_'.str()->random(8),
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
        $data = [
            'source_type' => BannerSourceType::CUSTOM_UPLOAD->value,
            'banner_template_id' => null,
            'position' => BannerPosition::HOMEPAGE_HERO->value,
            'title' => 'Custom Banner',
            'desktop_image_path' => 'banners/test/desktop.jpg',
            'link_type' => 'none',
            'sort_order' => 0,
            'status' => Banner::STATUS_ACTIVE,
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($data[$key]);

                continue;
            }

            $data[$key] = $value;
        }

        return Banner::query()->create($data);
    }
}
