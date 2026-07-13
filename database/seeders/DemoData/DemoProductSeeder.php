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
                foreach ($this->productsForShop((string) $shop->name) as $index => $product) {
                    $productNumber = $index + 1;
                    $rootCategoryId = (int) $shop->root_product_category_id;
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
                            'product_type' => 'simple',
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
                                'product_type' => 'simple',
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

                    $this->upsertDefaultVariant(
                        productId: $productId,
                        shopId: (int) $shop->id,
                        shopSlug: (string) $shop->slug,
                        productNumber: $productNumber,
                        productName: $product['name'],
                        mrp: $product['mrp'],
                        sellingPrice: $product['selling_price'],
                        stockQuantity: $product['stock_quantity'],
                        now: $now,
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productsForShop(string $shopName): array
    {
        $templates = [
            ['name' => 'Premium Cotton T-Shirt', 'category' => 'T-Shirts', 'brand' => 'Max Fashion', 'mrp' => 799, 'selling_price' => 599],
            ['name' => 'Classic Casual Shirt', 'category' => 'Shirts', 'brand' => 'Peter England', 'mrp' => 1299, 'selling_price' => 999],
            ['name' => 'Solid Polo T-Shirt', 'category' => 'Polo T-Shirts', 'brand' => 'Indian Terrain', 'mrp' => 999, 'selling_price' => 749],
            ['name' => 'Soft Fleece Sweatshirt', 'category' => 'Sweatshirts', 'brand' => 'Monte Carlo', 'mrp' => 1599, 'selling_price' => 1199],
            ['name' => 'Lightweight Casual Jacket', 'category' => 'Jackets', 'brand' => 'Being Human', 'mrp' => 2499, 'selling_price' => 1899],
            ['name' => 'Slim Fit Denim Jeans', 'category' => 'Jeans', 'brand' => 'Spykar', 'mrp' => 1999, 'selling_price' => 1499],
            ['name' => 'Formal Flat Front Trousers', 'category' => 'Trousers', 'brand' => 'Park Avenue', 'mrp' => 1799, 'selling_price' => 1399],
            ['name' => 'Everyday Cotton Shorts', 'category' => 'Shorts', 'brand' => 'Zudio', 'mrp' => 699, 'selling_price' => 499],
            ['name' => 'Comfort Track Pants', 'category' => 'Track Pants', 'brand' => 'Trends', 'mrp' => 899, 'selling_price' => 699],
            ['name' => 'Festive Cotton Kurta', 'category' => 'Kurtas', 'brand' => 'Fabindia', 'mrp' => 1499, 'selling_price' => 1099],
            ['name' => 'Elegant Kurta Set', 'category' => 'Kurta Sets', 'brand' => 'Biba', 'mrp' => 2499, 'selling_price' => 1999],
            ['name' => 'Wedding Sherwani Set', 'category' => 'Sherwanis', 'brand' => 'Manyavar', 'mrp' => 6999, 'selling_price' => 5999],
            ['name' => 'Cotton Innerwear Pack', 'category' => 'Innerwear', 'brand' => 'Lux Cozi', 'mrp' => 599, 'selling_price' => 449],
            ['name' => 'Printed Sleepwear Set', 'category' => 'Sleepwear', 'brand' => 'Westside', 'mrp' => 1199, 'selling_price' => 899],
            ['name' => 'Warm Winter Wear Set', 'category' => 'Winter Wear', 'brand' => 'Monte Carlo', 'mrp' => 2299, 'selling_price' => 1799],
            ['name' => 'Relaxed Fit Top', 'category' => 'Tops', 'brand' => 'Global Desi', 'mrp' => 999, 'selling_price' => 749],
            ['name' => 'Daily Wear Kurti', 'category' => 'Kurtis', 'brand' => 'Aurelia', 'mrp' => 1299, 'selling_price' => 999],
            ['name' => 'Silk Blend Saree', 'category' => 'Sarees', 'brand' => 'Nalli Silks', 'mrp' => 3499, 'selling_price' => 2999],
            ['name' => 'Designer Lehenga', 'category' => 'Lehengas', 'brand' => 'Meena Bazaar', 'mrp' => 5999, 'selling_price' => 4999],
            ['name' => 'A-Line Casual Dress', 'category' => 'Dresses', 'brand' => 'W for Woman', 'mrp' => 1899, 'selling_price' => 1499],
            ['name' => 'Stretch Cotton Leggings', 'category' => 'Leggings', 'brand' => 'Rangriti', 'mrp' => 699, 'selling_price' => 499],
            ['name' => 'Pleated Midi Skirt', 'category' => 'Skirts', 'brand' => 'Pantaloons', 'mrp' => 1199, 'selling_price' => 899],
            ['name' => 'Boys Graphic T-Shirt', 'category' => 'Boys', 'brand' => 'Gini & Jony', 'mrp' => 699, 'selling_price' => 499],
            ['name' => 'Girls Party Dress', 'category' => 'Girls', 'brand' => 'Gini & Jony', 'mrp' => 1299, 'selling_price' => 999],
            ['name' => 'Baby Clothing Gift Set', 'category' => 'Baby Clothing', 'brand' => 'Max Fashion', 'mrp' => 999, 'selling_price' => 799],
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
                'mrp' => $template['mrp'] + ($series * 25),
                'selling_price' => $template['selling_price'] + ($series * 20),
                'stock_quantity' => 20 + (($index * 7) % 80),
                'status' => $index % 10 === 0 ? 'draft' : 'active',
            ];
        }

        return $products;
    }

    /**
     * @return array{by_root: array<int, array<string, int>>, fallback_by_root: array<int, int>, fallback: int}
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

        foreach ($rows as $row) {
            if ($row->parent_id === null) {
                continue;
            }

            $rootId = $this->rootCategoryId((int) $row->id, $byId);
            $byRoot[$rootId][Str::lower((string) $row->name)] = (int) $row->id;
            $fallbackByRoot[$rootId] ??= (int) $row->id;
        }

        return [
            'by_root' => $byRoot,
            'fallback_by_root' => $fallbackByRoot,
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
     * @param array{by_root: array<int, array<string, int>>, fallback_by_root: array<int, int>, fallback: int} $categories
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

    private function upsertDefaultVariant(
        int $productId,
        int $shopId,
        string $shopSlug,
        int $productNumber,
        string $productName,
        int $mrp,
        int $sellingPrice,
        int $stockQuantity,
        mixed $now,
    ): void {
        $sku = 'DEMO-'.Str::upper(Str::limit(Str::slug($shopSlug, ''), 12, '')).'-'.str_pad((string) $productNumber, 3, '0', STR_PAD_LEFT);
        $existingVariantId = DB::table('product_variants')
            ->where('shop_id', $shopId)
            ->where('sku', $sku)
            ->value('id');

        $data = [
            'product_id' => $productId,
            'shop_id' => $shopId,
            'sku' => $sku,
            'barcode' => null,
            'name' => $productName,
            'mrp' => $mrp,
            'selling_price' => $sellingPrice,
            'cost_price' => max(1, $sellingPrice - 150),
            'stock_quantity' => $stockQuantity,
            'low_stock_threshold' => 5,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($existingVariantId === null) {
            DB::table('product_variants')->insert(array_merge($data, [
                'uuid' => (string) Str::uuid(),
                'created_at' => $now,
            ]));

            return;
        }

        DB::table('product_variants')
            ->where('id', $existingVariantId)
            ->update($data);
    }
}
