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
        $templates = [
            [
                'path' => ['Apparel', 'Men', 'T-Shirts'],
                'name' => 'Men T-Shirts Default Description',
                'short' => '{product_name} by {brand} is a comfortable {fit} {product_category} from {shop_name}, made for everyday casual wear.',
                'description' => "Refresh your wardrobe with {product_name}, a versatile {product_category} designed for comfort and easy styling.\n\nKey Features:\n- Brand: {brand}\n- Category: {category_path}\n- Material: {material}\n- Pattern: {pattern}\n- Fit: {fit}\n- Sleeve: {sleeve}\n- Neck: {neck}\n- Available colors: {colors}\n- Available sizes: {sizes}\n\nIdeal For:\n- {occasion}\n- Daily wear\n- Weekend outings\n\nPrice:\n- MRP: {mrp}\n- Selling Price: {selling_price}",
                'meta_title' => '{product_name} - Men T-Shirt by {brand}',
                'meta_description' => 'Shop {product_name}, a comfortable men t-shirt from {brand}, available at {shop_name}.',
            ],
            [
                'path' => ['Apparel', 'Men', 'Shirts'],
                'name' => 'Men Shirts Default Description',
                'short' => '{product_name} by {brand} is a polished {fit} {product_category} for smart casual and everyday wear.',
                'description' => "{product_name} brings together clean tailoring, reliable comfort, and versatile styling for modern menswear.\n\nKey Features:\n- Brand: {brand}\n- Category: {category_path}\n- Material: {material}\n- Pattern: {pattern}\n- Fit: {fit}\n- Sleeve: {sleeve}\n- Available colors: {colors}\n- Available sizes: {sizes}\n\nStyle Notes:\n- Suitable for {occasion}\n- Pairs well with jeans, chinos, or trousers\n\nPrice:\n- MRP: {mrp}\n- Selling Price: {selling_price}",
                'meta_title' => '{product_name} - Men Shirt by {brand}',
                'meta_description' => 'Buy {product_name}, a versatile men shirt from {brand}, at {shop_name}.',
            ],
            [
                'path' => ['Apparel', 'Women', 'Jeans'],
                'name' => 'Women Jeans Default Description',
                'short' => '{product_name} by {brand} is a stylish {fit} {product_category} designed for confident everyday dressing.',
                'description' => "{product_name} offers a flattering silhouette, dependable comfort, and styling flexibility for daily wear.\n\nKey Features:\n- Brand: {brand}\n- Category: {category_path}\n- Material: {material}\n- Fit: {fit}\n- Pattern: {pattern}\n- Available colors: {colors}\n- Available sizes: {sizes}\n\nBest Paired With:\n- Tops, shirts, kurtis, or casual tees\n- Sneakers, flats, or heels\n\nPrice:\n- MRP: {mrp}\n- Selling Price: {selling_price}",
                'meta_title' => '{product_name} - Women Jeans by {brand}',
                'meta_description' => 'Discover {product_name}, women jeans from {brand}, available at {shop_name}.',
            ],
            [
                'path' => ['Apparel', 'Women', 'Kurtis'],
                'name' => 'Women Kurtis Default Description',
                'short' => '{product_name} by {brand} is an elegant {product_category} made for {occasion} and everyday comfort.',
                'description' => "{product_name} blends traditional charm with practical comfort, making it a dependable choice for everyday and occasion wear.\n\nKey Features:\n- Brand: {brand}\n- Category: {category_path}\n- Material: {material}\n- Pattern: {pattern}\n- Fit: {fit}\n- Sleeve: {sleeve}\n- Neck: {neck}\n- Available colors: {colors}\n- Available sizes: {sizes}\n\nStyle Notes:\n- Suitable for {occasion}\n- Pair with leggings, palazzos, jeans, or ethnic bottoms\n\nPrice:\n- MRP: {mrp}\n- Selling Price: {selling_price}",
                'meta_title' => '{product_name} - Women Kurti by {brand}',
                'meta_description' => 'Shop {product_name}, an elegant women kurti from {brand}, at {shop_name}.',
            ],
        ];

        foreach ($templates as $index => $template) {
            $categoryId = $this->categoryIdByPath($template['path']);

            if ($categoryId === null) {
                continue;
            }

            $this->upsertTemplate($categoryId, $template, $index + 1);
        }
    }

    /**
     * @param array<int, string> $path
     */
    private function categoryIdByPath(array $path): ?int
    {
        $parentId = null;

        foreach ($path as $name) {
            $category = DB::table('product_categories')
                ->where('name', $name)
                ->where('parent_id', $parentId)
                ->whereNull('deleted_at')
                ->first(['id']);

            if (! $category) {
                return null;
            }

            $parentId = (int) $category->id;
        }

        return $parentId;
    }

    /**
     * @param array<string, mixed> $template
     */
    private function upsertTemplate(int $categoryId, array $template, int $sortOrder): void
    {
        $now = now();
        $exists = DB::table('product_description_templates')
            ->where('product_category_id', $categoryId)
            ->where('name', $template['name'])
            ->exists();

        DB::table('product_description_templates')->updateOrInsert(
            [
                'product_category_id' => $categoryId,
                'name' => $template['name'],
            ],
            [
                'short_description_template' => $template['short'],
                'description_template' => $template['description'],
                'meta_title_template' => $template['meta_title'],
                'meta_description_template' => $template['meta_description'],
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
