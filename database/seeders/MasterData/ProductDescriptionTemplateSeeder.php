<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductDescriptionTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $this->deactivateOldSeededTemplates($now);

        DB::table('product_categories')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (object $category, int $index) use ($now): void {
                $this->upsertGenericTemplate((int) $category->id, (string) $category->name, $index + 1, $now);
            });
    }

    private function deactivateOldSeededTemplates(mixed $now): void
    {
        DB::table('product_description_templates')
            ->whereIn('name', [
                'Men T-Shirts Default Description',
                'Men Shirts Default Description',
                'Women Jeans Default Description',
                'Women Kurtis Default Description',
            ])
            ->update([
                'status' => 'inactive',
                'updated_at' => $now,
            ]);
    }

    private function upsertGenericTemplate(int $categoryId, string $categoryName, int $sortOrder, mixed $now): void
    {
        $templateName = 'Generic '.$categoryName.' Description';
        $exists = DB::table('product_description_templates')
            ->where('product_category_id', $categoryId)
            ->where('name', $templateName)
            ->exists();

        DB::table('product_description_templates')->updateOrInsert(
            [
                'product_category_id' => $categoryId,
                'name' => $templateName,
            ],
            [
                'short_description_template' => '{product_name} by {brand} is a quality {product_category} from {shop_name}.',
                'description_template' => "{product_name} is selected for customers looking for dependable style, quality, and everyday usability.\n\nProduct Details:\n- Brand: {brand}\n- Category: {category_path}\n- Material: {material}\n- Pattern: {pattern}\n- Fit: {fit}\n- Sleeve: {sleeve}\n- Neck: {neck}\n- Occasion: {occasion}\n- Available colors: {colors}\n- Available sizes: {sizes}\n\nWhy customers like it:\n- Easy to browse and compare\n- Suitable for regular use or gifting\n- Available from {shop_name}",
                'meta_title_template' => '{product_name} by {brand}',
                'meta_description_template' => 'Shop {product_name}, a {product_category} from {brand}, available at {shop_name}.',
                'status' => 'active',
                'sort_order' => $sortOrder,
                'updated_at' => $now,
                ...($exists ? [] : [
                    'uuid' => (string) Str::uuid(),
                    'created_at' => $now,
                ]),
            ],
        );
    }
}
