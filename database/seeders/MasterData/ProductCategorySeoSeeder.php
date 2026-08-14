<?php

namespace Database\Seeders\MasterData;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeoSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;

        ProductCategory::query()
            ->with('parent.parent')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (ProductCategory $category) use (&$updated): void {
                $metadata = $this->metadataFor($category);
                $updates = [];

                if (! filled($category->meta_title)) {
                    $updates['meta_title'] = $metadata['title'];
                } elseif (trim((string) $category->meta_title) === $metadata['title'].' | WindowShop') {
                    $updates['meta_title'] = $metadata['title'];
                }

                if (! filled($category->meta_description)) {
                    $updates['meta_description'] = $metadata['description'];
                } elseif (trim((string) $category->meta_description) === $this->legacyWindowShopDescription($metadata['description'])) {
                    $updates['meta_description'] = $metadata['description'];
                }

                if (! filled($category->description)) {
                    $updates['description'] = $metadata['description'];
                }

                $disclaimer = $this->disclaimerFor($category);
                if ($disclaimer !== null && ! filled($category->product_disclaimer)) {
                    $updates['product_disclaimer'] = $disclaimer;
                }

                if ($updates === []) {
                    return;
                }

                $category->forceFill($updates)->saveQuietly();
                $updated++;
            });

        $this->command?->info("Product category SEO fields updated for {$updated} category/category records.");
    }

    /**
     * @return array{title: string, description: string}
     */
    private function metadataFor(ProductCategory $category): array
    {
        $parentName = $category->parent?->name;
        $rootName = $this->rootCategory($category)->name;
        $name = $category->name;

        if ($category->parent_id === null) {
            return $this->rootMetadata($name);
        }

        if ($rootName === 'Apparel' && $parentName === 'Apparel') {
            return $this->apparelGroupMetadata($name);
        }

        if ($rootName === 'Apparel' && in_array($parentName, ['Men', 'Women'], true)) {
            $audience = $parentName === 'Men' ? "Men's" : "Women's";

            return [
                'title' => "{$audience} {$name} Online",
                'description' => "Explore {$this->lowerAudience($parentName)} {$name} from local shops, including new styles, offers and everyday favourites.",
            ];
        }

        if ($name === 'Other') {
            return [
                'title' => "Other {$rootName} Options",
                'description' => "Browse more {$this->lower($rootName)} options from local shops.",
            ];
        }

        return [
            'title' => $name,
            'description' => "Explore {$this->lower($name)} from local shops.",
        ];
    }

    /**
     * @return array{title: string, description: string}
     */
    private function rootMetadata(string $name): array
    {
        return match ($name) {
            'Apparel' => [
                'title' => 'Apparel & Fashion',
                'description' => 'Explore apparel and fashion from local shops.',
            ],
            'Footwear' => [
                'title' => 'Footwear & Shoes',
                'description' => 'Explore footwear and shoes from local shops.',
            ],
            'Mobile & Electronics' => [
                'title' => 'Mobiles, Electronics & Accessories',
                'description' => 'Explore mobiles, electronics and accessories from local shops.',
            ],
            'Beauty & Cosmetics' => [
                'title' => 'Beauty & Cosmetics',
                'description' => 'Explore beauty, cosmetics and self-care products from local shops.',
            ],
            'Jewellery & Accessories' => [
                'title' => 'Jewellery & Accessories',
                'description' => 'Explore jewellery, bags and accessories from local shops.',
            ],
            'Grocery & Daily Needs' => [
                'title' => 'Grocery & Daily Needs',
                'description' => 'Explore grocery and daily needs from local shops.',
            ],
            'Home & Furniture' => [
                'title' => 'Home & Furniture',
                'description' => 'Explore furniture, decor and home essentials from local shops.',
            ],
            'Sports & Fitness' => [
                'title' => 'Sports & Fitness',
                'description' => 'Explore sports, fitness gear and activewear from local shops.',
            ],
            'Books & Stationery' => [
                'title' => 'Books & Stationery',
                'description' => 'Explore books, stationery and office supplies from local shops.',
            ],
            default => [
                'title' => $name,
                'description' => "Explore {$this->lower($name)} from local shops.",
            ],
        };
    }

    /**
     * @return array{title: string, description: string}
     */
    private function apparelGroupMetadata(string $name): array
    {
        return match ($name) {
            'Men' => [
                'title' => "Men's Fashion & Clothing",
                'description' => "Explore men's fashion, clothing and styles from local shops.",
            ],
            'Women' => [
                'title' => "Women's Fashion & Clothing",
                'description' => "Explore women's fashion, clothing and styles from local shops.",
            ],
            'Boys' => [
                'title' => "Boys' Clothing & Fashion",
                'description' => "Explore boys' clothing and everyday styles from local shops.",
            ],
            'Girls' => [
                'title' => "Girls' Clothing & Fashion",
                'description' => "Explore girls' clothing and everyday styles from local shops.",
            ],
            'Baby Clothing' => [
                'title' => 'Baby Clothing & Kids Wear',
                'description' => 'Explore baby clothing and kids wear from local shops.',
            ],
            'Unisex' => [
                'title' => 'Unisex Clothing & Fashion',
                'description' => 'Explore unisex clothing and fashion from local shops.',
            ],
            default => [
                'title' => "{$name} Fashion & Clothing",
                'description' => "Explore {$this->lower($name)} fashion and clothing from local shops.",
            ],
        };
    }

    private function rootCategory(ProductCategory $category): ProductCategory
    {
        while ($category->parent !== null) {
            $category = $category->parent;
        }

        return $category;
    }

    private function disclaimerFor(ProductCategory $category): ?string
    {
        $rootName = $this->rootCategory($category)->name;

        return match ($rootName) {
            'Apparel' => 'Product colors, fit, size and fabric appearance may vary slightly due to lighting, photography, screen settings and brand sizing. Please check size, fabric and care details before purchase.',
            'Footwear' => 'Footwear fit, color and material appearance may vary slightly by brand, style and screen settings. Please verify size, comfort and material details before purchase.',
            'Grocery & Daily Needs' => 'Please check product packaging for ingredients, manufacturing date, expiry, storage instructions, allergens and safety warnings before purchase or use.',
            'Beauty & Cosmetics' => 'Please check product packaging for ingredients, usage directions, expiry, storage instructions, suitability and safety warnings before use.',
            'Mobile & Electronics' => 'Specifications, warranty, accessories and box contents may vary by model, batch or shop. Please verify details before purchase.',
            default => null,
        };
    }

    private function lower(string $value): string
    {
        return mb_strtolower($value);
    }

    private function lowerAudience(string $value): string
    {
        return $value === 'Men' ? "men's" : "women's";
    }

    private function legacyWindowShopDescription(string $description): string
    {
        return rtrim($description, '.').' on WindowShop.';
    }
}
