<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Image\ImageVariantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    public function __construct(
        private readonly ImageVariantService $imageVariantService,
    ) {
    }

    public function imageAttributeMapping(Product $product): ?ProductCategoryAttributeGroup
    {
        return ProductCategoryAttributeGroup::query()
            ->with('group')
            ->where('root_product_category_id', $product->root_product_category_id)
            ->where('is_image_attribute', true)
            ->where('is_variant', true)
            ->first();
    }

    /**
     * @return Collection<int, ProductAttributeGroupValue>
     */
    public function selectableImageAttributeValues(Product $product): Collection
    {
        $mapping = $this->imageAttributeMapping($product);

        if (! $mapping) {
            return collect();
        }

        return ProductAttributeGroupValue::query()
            ->where('product_attribute_group_id', $mapping->product_attribute_group_id)
            ->where('status', 'active')
            ->whereHas('productAttributes', fn ($query) => $query->where('product_id', $product->getKey()))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function upload(Product $product, array $files, ?int $attributeValueId, int $actorId): void
    {
        if ($files === []) {
            return;
        }

        $this->assertCanAddActiveImages($product, count($files));
        $this->assertValidAttributeValue($product, $attributeValueId);

        DB::transaction(function () use ($product, $files, $attributeValueId, $actorId): void {
            $nextSortOrder = ((int) $product->images()->max('sort_order')) + 1;

            foreach ($files as $file) {
                $imageUuid = (string) Str::uuid();
                $directory = "products/{$product->uuid}/images/{$imageUuid}";
                $paths = $this->imageVariantService->store($file, 'product', $directory);

                $image = ProductImage::query()->create([
                    'uuid' => $imageUuid,
                    'product_id' => $product->getKey(),
                    'image_path' => $paths['web'] ?? array_values($paths)[0],
                    'thumbnail_path' => $paths['thumb'] ?? array_values($paths)[0],
                    'title' => null,
                    'alt_text' => null,
                    'sort_order' => $nextSortOrder++,
                    'is_primary' => false,
                    'status' => 'active',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                if ($attributeValueId !== null) {
                    $image->attributeValues()->sync([$attributeValueId]);
                }

                if ($product->primary_image_id === null) {
                    $this->setPrimaryImage($product, $image, $actorId);
                }
            }
        });
    }

    /**
     * @param array<int|string, array<string, mixed>> $rows
     */
    public function update(Product $product, array $rows, ?int $primaryImageId, int $actorId): void
    {
        DB::transaction(function () use ($product, $rows, $primaryImageId, $actorId): void {
            foreach ($rows as $imageId => $row) {
                $image = ProductImage::query()
                    ->where('product_id', $product->getKey())
                    ->whereKey((int) $imageId)
                    ->first();

                if (! $image) {
                    continue;
                }

                $status = (string) ($row['status'] ?? $image->status);

                $image->forceFill([
                    'title' => $this->nullable($row['title'] ?? null),
                    'alt_text' => $this->nullable($row['alt_text'] ?? null),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'status' => in_array($status, ['active', 'inactive'], true) ? $status : $image->status,
                    'updated_by' => $actorId,
                ])->save();
            }

            $this->assertCanHaveActiveImages($product);

            if ($primaryImageId !== null) {
                $primary = ProductImage::query()
                    ->whereKey($primaryImageId)
                    ->where('product_id', $product->getKey())
                    ->first();

                if (! $primary) {
                    throw ValidationException::withMessages([
                        'primary_image_id' => 'The selected primary image must belong to this product and be active.',
                    ]);
                }

                if ($primary->status === 'active') {
                    $this->setPrimaryImage($product, $primary, $actorId);
                    return;
                }

                $this->refreshPrimaryImage($product, $actorId);
                return;
            }

            $this->refreshPrimaryImage($product, $actorId);
        });
    }

    public function delete(Product $product, ProductImage $image, int $actorId): void
    {
        if ((int) $image->product_id !== (int) $product->getKey()) {
            abort(404);
        }

        DB::transaction(function () use ($product, $image, $actorId): void {
            $image->forceFill([
                'deleted_by' => $actorId,
                'updated_by' => $actorId,
            ])->save();

            $image->delete();
            Storage::disk('public')->deleteDirectory(dirname($image->image_path));

            $this->refreshPrimaryImage($product, $actorId);
        });
    }

    public function refreshPrimaryImage(Product $product, int $actorId): void
    {
        $product->refresh();

        if ($product->primary_image_id && $this->activeImageForProduct($product, (int) $product->primary_image_id)) {
            ProductImage::query()
                ->where('product_id', $product->getKey())
                ->update(['is_primary' => false]);
            ProductImage::query()
                ->whereKey($product->primary_image_id)
                ->update(['is_primary' => true]);
            return;
        }

        $next = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($next) {
            $this->setPrimaryImage($product, $next, $actorId);
            return;
        }

        ProductImage::query()
            ->where('product_id', $product->getKey())
            ->update(['is_primary' => false]);

        $product->forceFill([
            'primary_image_id' => null,
            'updated_by' => $actorId,
        ])->save();
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function galleryForVariant(Product $product, ?ProductVariant $variant = null): Collection
    {
        $imageAttributeValueId = $this->imageAttributeValueIdForVariant($product, $variant);

        if ($imageAttributeValueId !== null) {
            $mappedImages = $this->activeImages($product)
                ->whereHas('attributeValues', fn ($query) => $query->whereKey($imageAttributeValueId))
                ->get();

            if ($mappedImages->isNotEmpty()) {
                return $mappedImages;
            }
        }

        $generalImages = $this->activeImages($product)
            ->whereDoesntHave('attributeValues')
            ->get();

        if ($generalImages->isNotEmpty()) {
            return $generalImages;
        }

        if ($product->primary_image_id) {
            $primary = $this->activeImageForProduct($product, (int) $product->primary_image_id);

            if ($primary) {
                return collect([$primary]);
            }
        }

        return collect();
    }

    private function imageAttributeValueIdForVariant(Product $product, ?ProductVariant $variant): ?int
    {
        if (! $variant) {
            return null;
        }

        $mapping = $this->imageAttributeMapping($product);

        if (! $mapping) {
            return null;
        }

        return $variant->attributes()
            ->where('product_attribute_group_id', $mapping->product_attribute_group_id)
            ->value('product_attribute_group_value_id');
    }

    private function activeImages(Product $product)
    {
        return ProductImage::query()
            ->with('attributeValues')
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function setPrimaryImage(Product $product, ProductImage $image, int $actorId): void
    {
        ProductImage::query()
            ->where('product_id', $product->getKey())
            ->update(['is_primary' => false]);

        $image->forceFill([
            'is_primary' => true,
            'updated_by' => $actorId,
        ])->save();

        $product->forceFill([
            'primary_image_id' => $image->getKey(),
            'updated_by' => $actorId,
        ])->save();
    }

    private function activeImageForProduct(Product $product, int $imageId): ?ProductImage
    {
        return ProductImage::query()
            ->whereKey($imageId)
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->first();
    }

    private function assertCanAddActiveImages(Product $product, int $count): void
    {
        $activeCount = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->count();

        if ($activeCount + $count > 8) {
            throw ValidationException::withMessages([
                'images' => 'A product can have a maximum of 8 active images.',
            ]);
        }
    }

    private function assertCanHaveActiveImages(Product $product): void
    {
        $activeCount = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->count();

        if ($activeCount > 8) {
            throw ValidationException::withMessages([
                'images' => 'A product can have a maximum of 8 active images.',
            ]);
        }
    }

    private function assertValidAttributeValue(Product $product, ?int $attributeValueId): void
    {
        if ($attributeValueId === null) {
            return;
        }

        if (! $this->selectableImageAttributeValues($product)->pluck('id')->contains($attributeValueId)) {
            throw ValidationException::withMessages([
                'attribute_value_id' => 'The selected image attribute value is not available for this product.',
            ]);
        }
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
