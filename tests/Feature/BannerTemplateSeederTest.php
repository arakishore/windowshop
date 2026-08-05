<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\BannerTemplate;
use Database\Seeders\BannerTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class BannerTemplateSeederTest extends TestCase
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

    public function test_banner_template_seeder_creates_v1_pack_metadata(): void
    {
        $this->seed(BannerTemplateSeeder::class);

        $this->assertSame(49, BannerTemplate::query()->count());
        $expectedCounts = [
            BannerTemplateCategory::GENERAL->value => 10,
            BannerTemplateCategory::FESTIVAL->value => 12,
            BannerTemplateCategory::SEASONAL->value => 6,
            BannerTemplateCategory::FASHION->value => 6,
            BannerTemplateCategory::ELECTRONICS->value => 5,
            BannerTemplateCategory::GROCERY->value => 5,
            BannerTemplateCategory::SERVICES->value => 5,
        ];
        $actualCounts = BannerTemplate::query()
            ->selectRaw('category, count(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category')
            ->map(fn ($count): int => (int) $count)
            ->all();
        ksort($expectedCounts);
        ksort($actualCounts);

        $this->assertSame($expectedCounts, $actualCounts);

        $this->assertDatabaseHas('banner_templates', [
            'code' => 'generic_001',
            'category' => BannerTemplateCategory::GENERAL->value,
            'name' => 'Up to 50% OFF',
            'default_title' => 'Up to 50% OFF',
            'default_subtitle' => 'Save big on selected products.',
            'default_button_text' => 'Shop Now',
            'desktop_image_path' => 'banner-templates/generic_001/desktop.webp',
            'mobile_image_path' => 'banner-templates/generic_001/mobile.webp',
            'default_position' => BannerPosition::STORE_HERO->value,
            'availability' => BannerTemplateAvailability::BOTH->value,
            'status' => BannerTemplate::STATUS_ACTIVE,
            'sort_order' => 10,
        ]);

        $this->assertDatabaseHas('banner_templates', [
            'code' => 'festival_001',
            'event_code' => 'diwali',
            'start_offset_days' => -10,
            'end_offset_days' => 2,
        ]);
    }

    public function test_banner_template_seeder_is_idempotent_and_restores_seeded_templates(): void
    {
        $this->seed(BannerTemplateSeeder::class);
        $template = BannerTemplate::query()->where('code', 'generic_001')->firstOrFail();
        $uuid = $template->uuid;

        $template->delete();

        $this->seed(BannerTemplateSeeder::class);
        $this->seed(BannerTemplateSeeder::class);

        $this->assertSame(49, BannerTemplate::query()->count());
        $this->assertSame(49, BannerTemplate::withTrashed()->distinct('code')->count('code'));
        $this->assertDatabaseHas('banner_templates', [
            'code' => 'generic_001',
            'uuid' => $uuid,
            'deleted_at' => null,
        ]);
    }
}
