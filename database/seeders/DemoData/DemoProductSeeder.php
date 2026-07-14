<?php

namespace Database\Seeders\DemoData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $shops = DB::table('shops')
                ->select('id', 'merchant_id', 'name', 'slug', 'root_product_category_id')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            if ($shops->isEmpty()) {
                throw new RuntimeException('Demo shops must exist before seeding demo products.');
            }

            $productCategories = $this->productCategories();
            $brands = $this->brands();

            foreach ($shops as $shop) {
                $rootCategoryId = (int) $shop->root_product_category_id;
                $rootCategoryName = $productCategories['root_names'][$rootCategoryId] ?? '';

                foreach ($this->productsForShop((string) $shop->name, $rootCategoryName) as $product) {
                    $productCategoryId = $this->categoryIdForProduct($productCategories, $product['category'], $rootCategoryId);
                    $brandId = $this->brandIdForProduct($brands, $product['brand']);

                    $existingProductId = DB::table('products')
                        ->where('shop_id', $shop->id)
                        ->where('product_name', $product['name'])
                        ->value('id');

                    if ($existingProductId === null) {
                        $productId = (int) DB::table('products')->insertGetId([
                            'uuid' => (string) Str::uuid(),
                            'merchant_id' => $shop->merchant_id,
                            'shop_id' => $shop->id,
                            'root_product_category_id' => $rootCategoryId,
                            'product_category_id' => $productCategoryId,
                            'brand_id' => $brandId,
                            'product_name' => $product['name'],
                            'slug' => 'pending-'.Str::uuid(),
                            'short_description' => $product['short_description'],
                            'description' => $product['description'],
                            'meta_title' => $product['name'],
                            'meta_description' => $product['short_description'],
                            'status' => $product['status'],
                            'published_at' => $product['status'] === 'active' ? $now : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $productId = (int) $existingProductId;

                        DB::table('products')
                            ->where('id', $productId)
                            ->update([
                                'merchant_id' => $shop->merchant_id,
                                'root_product_category_id' => $rootCategoryId,
                                'product_category_id' => $productCategoryId,
                                'brand_id' => $brandId,
                                'short_description' => $product['short_description'],
                                'description' => $product['description'],
                                'meta_title' => $product['name'],
                                'meta_description' => $product['short_description'],
                                'status' => $product['status'],
                                'published_at' => $product['status'] === 'active' ? $now : null,
                                'deleted_at' => null,
                                'updated_at' => $now,
                            ]);
                    }

                    DB::table('products')
                        ->where('id', $productId)
                        ->update([
                            'slug' => $this->productSlug($product['name'], $productId),
                            'updated_at' => $now,
                        ]);
                }
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productsForShop(string $shopName, string $rootCategoryName): array
    {
        return match ($rootCategoryName) {
            'Beauty & Cosmetics' => $this->beautyProductsForShop($shopName),
            'Jewellery & Accessories' => $this->bagProductsForShop($shopName),
            default => $this->apparelProductsForShop($shopName),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function apparelProductsForShop(string $shopName): array
    {
        $templates = [
            ['name' => 'Premium Cotton T-Shirt', 'category' => 'T-Shirts', 'brand' => 'Max Fashion'],
            ['name' => 'Classic Casual Shirt', 'category' => 'Shirts', 'brand' => 'Peter England'],
            ['name' => 'Solid Polo T-Shirt', 'category' => 'Polo T-Shirts', 'brand' => 'Indian Terrain'],
            ['name' => 'Soft Fleece Sweatshirt', 'category' => 'Sweatshirts', 'brand' => 'Monte Carlo'],
            ['name' => 'Lightweight Casual Jacket', 'category' => 'Jackets', 'brand' => 'Being Human'],
            ['name' => 'Slim Fit Denim Jeans', 'category' => 'Jeans', 'brand' => 'Spykar'],
            ['name' => 'Formal Flat Front Trousers', 'category' => 'Trousers', 'brand' => 'Park Avenue'],
            ['name' => 'Everyday Cotton Shorts', 'category' => 'Shorts', 'brand' => 'Zudio'],
            ['name' => 'Comfort Track Pants', 'category' => 'Track Pants', 'brand' => 'Trends'],
            ['name' => 'Festive Cotton Kurta', 'category' => 'Kurtas', 'brand' => 'Fabindia'],
            ['name' => 'Elegant Kurta Set', 'category' => 'Kurta Sets', 'brand' => 'Biba'],
            ['name' => 'Wedding Sherwani Set', 'category' => 'Sherwanis', 'brand' => 'Manyavar'],
            ['name' => 'Cotton Innerwear Pack', 'category' => 'Innerwear', 'brand' => 'Lux Cozi'],
            ['name' => 'Printed Sleepwear Set', 'category' => 'Sleepwear', 'brand' => 'Westside'],
            ['name' => 'Warm Winter Wear Set', 'category' => 'Winter Wear', 'brand' => 'Monte Carlo'],
            ['name' => 'Relaxed Fit Top', 'category' => 'Tops', 'brand' => 'Global Desi'],
            ['name' => 'Daily Wear Kurti', 'category' => 'Kurtis', 'brand' => 'Aurelia'],
            ['name' => 'Silk Blend Saree', 'category' => 'Sarees', 'brand' => 'Nalli Silks'],
            ['name' => 'Designer Lehenga', 'category' => 'Lehengas', 'brand' => 'Meena Bazaar'],
            ['name' => 'A-Line Casual Dress', 'category' => 'Dresses', 'brand' => 'W for Woman'],
            ['name' => 'Stretch Cotton Leggings', 'category' => 'Leggings', 'brand' => 'Rangriti'],
            ['name' => 'Pleated Midi Skirt', 'category' => 'Skirts', 'brand' => 'Pantaloons'],
            ['name' => 'Boys Graphic T-Shirt', 'category' => 'Boys', 'brand' => 'Gini & Jony'],
            ['name' => 'Girls Party Dress', 'category' => 'Girls', 'brand' => 'Gini & Jony'],
            ['name' => 'Baby Clothing Gift Set', 'category' => 'Baby Clothing', 'brand' => 'Max Fashion'],
        ];

        $colors = ['Black', 'White', 'Navy', 'Maroon', 'Olive', 'Beige', 'Grey', 'Pink', 'Blue', 'Green'];
        $fits = ['Regular', 'Slim', 'Relaxed', 'Comfort', 'Classic'];
        $products = [];

        for ($index = 0; $index < 50; $index++) {
            $template = $templates[$index % count($templates)];
            $color = $colors[$index % count($colors)];
            $fit = $fits[$index % count($fits)];
            $series = intdiv($index, count($templates)) + 1;
            $name = "{$color} {$fit} {$template['name']} {$series}";

            $products[] = [
                'name' => $name,
                'category' => $template['category'],
                'brand' => $template['brand'],
                'short_description' => "{$fit} {$template['name']} from {$shopName}.",
                'description' => "{$name} is demo catalogue data for {$shopName}. It is suitable for product listing, filtering, variant, and checkout workflow testing.",
                'status' => $index % 10 === 0 ? 'draft' : 'active',
            ];
        }

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function beautyProductsForShop(string $shopName): array
    {
        $templates = [
            ['name' => 'Matte Liquid Lipstick', 'category' => 'Makeup', 'brand' => 'Lakme'],
            ['name' => 'Long Wear Foundation', 'category' => 'Makeup', 'brand' => 'Maybelline'],
            ['name' => 'Waterproof Kajal', 'category' => 'Makeup', 'brand' => 'Lakme'],
            ['name' => 'Compact Powder', 'category' => 'Makeup', 'brand' => 'Colorbar'],
            ['name' => 'Blush Palette', 'category' => 'Makeup', 'brand' => 'Nykaa Cosmetics'],
            ['name' => 'Eyeshadow Palette', 'category' => 'Makeup', 'brand' => 'Colorbar'],
            ['name' => 'Nail Enamel Set', 'category' => 'Makeup', 'brand' => 'Nykaa Cosmetics'],
            ['name' => 'Makeup Primer', 'category' => 'Makeup', 'brand' => 'Maybelline'],
            ['name' => 'Brow Definer Pencil', 'category' => 'Makeup', 'brand' => 'Lakme'],
            ['name' => 'Makeup Brush Kit', 'category' => 'Makeup', 'brand' => 'Colorbar'],
        ];

        $shades = ['Rose', 'Nude', 'Berry', 'Coral', 'Ivory', 'Sand', 'Caramel', 'Plum', 'Peach', 'Ruby'];
        $finishes = ['Matte', 'Dewy', 'Satin', 'Natural', 'Glossy'];

        return $this->buildDemoProducts($templates, $shades, $finishes, $shopName, 30);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bagProductsForShop(string $shopName): array
    {
        $templates = [
            ['name' => 'Structured Tote Bag', 'category' => 'Bags', 'brand' => 'Caprese'],
            ['name' => 'Quilted Sling Bag', 'category' => 'Bags', 'brand' => 'Lavie'],
            ['name' => 'Office Shoulder Bag', 'category' => 'Bags', 'brand' => 'Lino Perros'],
            ['name' => 'Mini Crossbody Bag', 'category' => 'Bags', 'brand' => 'Caprese'],
            ['name' => 'Everyday Backpack', 'category' => 'Bags', 'brand' => 'Lavie'],
            ['name' => 'Party Clutch', 'category' => 'Bags', 'brand' => 'Lino Perros'],
            ['name' => 'Travel Duffel Bag', 'category' => 'Bags', 'brand' => 'Mochi'],
            ['name' => 'Laptop Messenger Bag', 'category' => 'Bags', 'brand' => 'Caprese'],
            ['name' => 'Drawstring Bucket Bag', 'category' => 'Bags', 'brand' => 'Lavie'],
            ['name' => 'Wallet and Pouch Set', 'category' => 'Bags', 'brand' => 'Lino Perros'],
        ];

        $colors = ['Black', 'Tan', 'Navy', 'Maroon', 'Beige', 'Olive', 'Grey', 'Pink', 'Brown', 'Cream'];
        $styles = ['Classic', 'Premium', 'Textured', 'Compact', 'Statement'];

        return $this->buildDemoProducts($templates, $colors, $styles, $shopName, 30);
    }

    /**
     * @param array<int, array{name: string, category: string, brand: string}> $templates
     * @param array<int, string> $prefixes
     * @param array<int, string> $modifiers
     * @return array<int, array<string, mixed>>
     */
    private function buildDemoProducts(array $templates, array $prefixes, array $modifiers, string $shopName, int $count): array
    {
        $products = [];

        for ($index = 0; $index < $count; $index++) {
            $template = $templates[$index % count($templates)];
            $prefix = $prefixes[$index % count($prefixes)];
            $modifier = $modifiers[$index % count($modifiers)];
            $series = intdiv($index, count($templates)) + 1;
            $name = "{$prefix} {$modifier} {$template['name']} {$series}";

            $products[] = [
                'name' => $name,
                'category' => $template['category'],
                'brand' => $template['brand'],
                'short_description' => "{$modifier} {$template['name']} from {$shopName}.",
                'description' => "{$name} is demo catalogue data for {$shopName}. It is suitable for product listing, filtering, variant, and checkout workflow testing.",
                'status' => $index % 10 === 0 ? 'draft' : 'active',
            ];
        }

        return $products;
    }

    /**
     * @return array{by_root: array<int, array<string, int>>, fallback_by_root: array<int, int>, root_names: array<int, string>, fallback: int}
     */
    private function productCategories(): array
    {
        $rows = DB::table('product_categories')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        if ($rows->isEmpty()) {
            throw new RuntimeException('Active product categories must exist before seeding demo products.');
        }

        $byId = $rows->keyBy('id');
        $byRoot = [];
        $fallbackByRoot = [];
        $rootNames = [];

        foreach ($rows as $row) {
            if ($row->parent_id === null) {
                $rootNames[(int) $row->id] = (string) $row->name;
                continue;
            }

            $rootId = $this->rootCategoryId((int) $row->id, $byId);
            $byRoot[$rootId][Str::lower((string) $row->name)] = (int) $row->id;
            $fallbackByRoot[$rootId] ??= (int) $row->id;
        }

        return [
            'by_root' => $byRoot,
            'fallback_by_root' => $fallbackByRoot,
            'root_names' => $rootNames,
            'fallback' => (int) $rows->first()->id,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function brands(): array
    {
        return DB::table('brands')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name): array => [Str::lower((string) $name) => (int) $id])
            ->all();
    }

    /**
     * @param array{by_root: array<int, array<string, int>>, fallback_by_root: array<int, int>, root_names: array<int, string>, fallback: int} $categories
     */
    private function categoryIdForProduct(array $categories, string $name, int $rootCategoryId): int
    {
        $rootCategories = $categories['by_root'][$rootCategoryId] ?? [];

        return $rootCategories[Str::lower($name)]
            ?? $rootCategories['unisex']
            ?? $categories['fallback_by_root'][$rootCategoryId]
            ?? $categories['fallback'];
    }

    private function rootCategoryId(int $categoryId, mixed $byId): int
    {
        $visited = [];
        $current = $byId->get($categoryId);

        while ($current && $current->parent_id !== null && ! in_array((int) $current->id, $visited, true)) {
            $visited[] = (int) $current->id;
            $current = $byId->get((int) $current->parent_id);
        }

        return (int) ($current->id ?? $categoryId);
    }

    /**
     * @param array<string, int> $brands
     */
    private function brandIdForProduct(array $brands, string $name): ?int
    {
        return $brands[Str::lower($name)]
            ?? $brands['other']
            ?? null;
    }

    private function productSlug(string $name, int $productId): string
    {
        return (Str::slug($name) ?: 'product').'-'.$productId;
    }

}
