<?php

namespace Tests\Feature;

use Database\Seeders\MasterData\SystemFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ProductDescriptionTemplateSeederTest extends TestCase
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

    public function test_generic_description_templates_are_seeded_for_all_active_categories_without_price_tags(): void
    {
        $this->seed(SystemFoundationSeeder::class);

        $categoryCount = DB::table('product_categories')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        $templateRows = DB::table('product_description_templates')
            ->where('status', 'active')
            ->get([
                'product_category_id',
                'name',
                'short_description_template',
                'description_template',
                'meta_title_template',
                'meta_description_template',
            ]);

        $this->assertSame($categoryCount, $templateRows->count());
        $this->assertSame(
            $categoryCount,
            $templateRows->pluck('product_category_id')->unique()->count(),
        );

        foreach ($templateRows as $template) {
            $combinedTemplateText = implode(' ', [
                $template->name,
                $template->short_description_template,
                $template->description_template,
                $template->meta_title_template,
                $template->meta_description_template,
            ]);

            $this->assertStringStartsWith('Generic ', $template->name);
            $this->assertStringNotContainsString('{mrp}', $combinedTemplateText);
            $this->assertStringNotContainsString('{selling_price}', $combinedTemplateText);
            $this->assertStringNotContainsString('MRP', $combinedTemplateText);
            $this->assertStringNotContainsString('Selling Price', $combinedTemplateText);
        }
    }
}
