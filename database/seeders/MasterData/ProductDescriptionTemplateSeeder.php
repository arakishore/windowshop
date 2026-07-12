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
        $categoryId = DB::table('shop_categories')
            ->where('slug', 'apparel')
            ->value('id');

        if ($categoryId === null) {
            return;
        }

        $now = now();
        $exists = DB::table('product_description_templates')
            ->where('shop_category_id', $categoryId)
            ->where('name', 'Default Apparel Description')
            ->exists();

        DB::table('product_description_templates')->updateOrInsert(
            [
                'shop_category_id' => $categoryId,
                'name' => 'Default Apparel Description',
            ],
            [
                'short_description_template' => 'Discover this stylish {color} {category}, crafted from premium {material} for everyday comfort and elegance. Available in sizes {sizes}.',
                'description_template' => "Introducing the {product_name}, a perfect combination of style, comfort, and quality. Made from premium {material}, this {color} {category} is designed for customers who appreciate elegant fashion without compromising on comfort.\n\nThe lightweight fabric, comfortable fit, and versatile design make it suitable for everyday wear, office wear, family gatherings, festive occasions, and casual outings.\n\nKey Features:\n- Premium {material} fabric\n- Stylish {color} finish\n- Soft and comfortable\n- Durable stitching\n- Easy to maintain\n- Available in sizes: {sizes}\n\nWhy You'll Love It:\n- Comfortable all-day wear\n- Elegant and modern design\n- Suitable for multiple occasions\n- Excellent value for money\n\nPackage Includes:\n1 x {product_name}\n\nDisclaimer:\nActual product color may vary slightly due to photographic lighting or your device display settings.",
                'status' => 'active',
                'sort_order' => 1,
                'updated_at' => $now,
                ...($exists ? [] : [
                    'uuid' => (string) Str::uuid(),
                    'created_at' => $now,
                ]),
            ],
        );
    }
}
