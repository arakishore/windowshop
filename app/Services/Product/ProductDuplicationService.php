<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductDuplicationService
{
    public function __construct(
        private readonly ProductVariantManagementService $variantManagementService,
    ) {
    }

    public function duplicate(Product $source, User $user, ?Shop $destinationShop = null): Product
    {
        $source->loadMissing([
            'attributes',
            'variants.attributes',
            'images.attributeValues',
            'shop',
        ]);

        $shop = $destinationShop ?? $source->shop;

        return DB::transaction(function () use ($source, $user, $shop): Product {
            $duplicate = Product::query()->create([
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
                'root_product_category_id' => $shop->root_product_category_id,
                'product_category_id' => $source->product_category_id,
                'brand_id' => $source->brand_id,
                'product_name' => "{$source->product_name} - Copy",
                'slug' => 'pending-'.Str::uuid()->toString(),
                'short_description' => $source->short_description,
                'description' => $source->description,
                'meta_title' => $source->meta_title,
                'meta_description' => $source->meta_description,
                'status' => 'draft',
                'published_at' => null,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $duplicate->updateQuietly([
                'slug' => $duplicate->slugFromName(),
            ]);

            $this->copyAttributes($source, $duplicate);
            $this->copyVariants($source, $duplicate, $user);
            $this->copyImages($source, $duplicate, $user);

            if ($duplicate->variants()->exists()) {
                $this->variantManagementService->ensureDefaultVariant($duplicate, $user);
            } else {
                $this->variantManagementService->ensureBaseVariant($duplicate, $user);
            }

            return $duplicate->refresh();
        });
    }

    private function copyAttributes(Product $source, Product $duplicate): void
    {
        foreach ($source->attributes as $attribute) {
            ProductAttribute::query()->create([
                'product_id' => $duplicate->getKey(),
                'product_attribute_group_id' => $attribute->product_attribute_group_id,
                'product_attribute_group_value_id' => $attribute->product_attribute_group_value_id,
            ]);
        }
    }

    private function copyVariants(Product $source, Product $duplicate, User $user): void
    {
        foreach ($source->variants as $variant) {
            $newVariant = ProductVariant::query()->create([
                'product_id' => $duplicate->getKey(),
                'shop_id' => $duplicate->shop_id,
                'sku' => null,
                'barcode' => null,
                'name' => $variant->name,
                'mrp' => $variant->mrp,
                'selling_price' => $variant->selling_price,
                'cost_price' => $variant->cost_price,
                'stock_quantity' => 0,
                'low_stock_threshold' => $variant->low_stock_threshold,
                'is_default' => $variant->is_default,
                'sort_order' => $variant->sort_order,
                'status' => $variant->status,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            foreach ($variant->attributes as $attribute) {
                ProductVariantAttribute::query()->create([
                    'product_variant_id' => $newVariant->getKey(),
                    'product_attribute_group_id' => $attribute->product_attribute_group_id,
                    'product_attribute_group_value_id' => $attribute->product_attribute_group_value_id,
                ]);
            }
        }
    }

    private function copyImages(Product $source, Product $duplicate, User $user): void
    {
        $primaryImageId = null;

        foreach ($source->images as $image) {
            $newImage = ProductImage::query()->create([
                'product_id' => $duplicate->getKey(),
                'image_path' => '',
                'thumbnail_path' => null,
                'title' => $image->title,
                'alt_text' => $image->alt_text,
                'is_primary' => false,
                'sort_order' => $image->sort_order,
                'status' => $image->status,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $paths = $this->copyImageFiles($image, $newImage, $duplicate);
            $newImage->forceFill([
                'image_path' => $paths['web'] ?? $paths['image'] ?? '',
                'thumbnail_path' => $paths['thumb'] ?? $paths['image'] ?? null,
            ])->save();
            $newImage->attributeValues()->sync($image->attributeValues->pluck('id')->all());

            if ($image->is_primary || (int) $source->primary_image_id === (int) $image->getKey()) {
                $primaryImageId = $newImage->getKey();
            }
        }

        if ($primaryImageId === null) {
            $primaryImageId = ProductImage::query()
                ->where('product_id', $duplicate->getKey())
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');
        }

        if ($primaryImageId !== null) {
            ProductImage::query()
                ->where('product_id', $duplicate->getKey())
                ->update(['is_primary' => false]);
            ProductImage::query()
                ->whereKey($primaryImageId)
                ->update(['is_primary' => true]);

            $duplicate->forceFill([
                'primary_image_id' => $primaryImageId,
                'updated_by' => $user->getKey(),
            ])->save();
        }
    }

    /**
     * @return array<string, string>
     */
    private function copyImageFiles(ProductImage $sourceImage, ProductImage $newImage, Product $duplicate): array
    {
        $disk = Storage::disk('public');
        $destinationDirectory = $this->imageDirectory($duplicate);
        $copiedPaths = [];

        $disk->makeDirectory($destinationDirectory);

        foreach ($this->sourceImageFiles($sourceImage) as $sourcePath) {
            if (! $disk->exists($sourcePath)) {
                continue;
            }

            $suffix = $this->imageSuffix($sourceImage, $sourcePath);
            $destinationPath = "{$destinationDirectory}/".$this->imageFilenamePrefix($duplicate, $newImage)."-{$suffix}";

            $disk->copy($sourcePath, $destinationPath);

            $key = pathinfo($suffix, PATHINFO_FILENAME) ?: 'image';
            $copiedPaths[$key] = $destinationPath;
        }

        return $copiedPaths;
    }

    /**
     * @return array<int, string>
     */
    private function sourceImageFiles(ProductImage $image): array
    {
        if (! $image->image_path) {
            return [];
        }

        $disk = Storage::disk('public');
        $directory = dirname($image->image_path);
        $prefix = "p{$image->product_id}-img{$image->getKey()}-";
        $prefixedFiles = collect($disk->files($directory))
            ->filter(fn (string $path): bool => str_starts_with(basename($path), $prefix))
            ->values();

        if ($prefixedFiles->isNotEmpty()) {
            return $prefixedFiles->all();
        }

        return collect([$image->image_path, $image->thumbnail_path])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function imageSuffix(ProductImage $image, string $path): string
    {
        $prefix = "p{$image->product_id}-img{$image->getKey()}-";
        $basename = basename($path);

        if (str_starts_with($basename, $prefix)) {
            return Str::after($basename, $prefix);
        }

        return $path === $image->thumbnail_path ? 'thumb.webp' : 'web.webp';
    }

    private function imageDirectory(Product $product): string
    {
        return "products/{$product->getKey()}-{$product->uuid}/images";
    }

    private function imageFilenamePrefix(Product $product, ProductImage $image): string
    {
        return "p{$product->getKey()}-img{$image->getKey()}";
    }
}
