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

        $this->uploadGroups($product, [$attributeValueId ?? 'entire' => $files], $actorId);
    }

    /**
     * @param array<int|string, array<int, UploadedFile>> $groups
     */
    public function uploadGroups(Product $product, array $groups, int $actorId): void
    {
        $normalizedGroups = $this->normalizeUploadGroups($product, $groups);
        $fileCount = collect($normalizedGroups)->sum(fn (array $group): int => count($group['files']));

        if ($fileCount === 0) {
            return;
        }

        $this->assertCanAddUploadGroups($product, $normalizedGroups);

        DB::transaction(function () use ($product, $normalizedGroups, $actorId): void {
            $nextSortOrder = ((int) $product->images()->max('sort_order')) + 1;

            foreach ($normalizedGroups as $group) {
                foreach ($group['files'] as $file) {
                    $imageUuid = (string) Str::uuid();
                    $image = ProductImage::query()->create([
                        'uuid' => $imageUuid,
                        'product_id' => $product->getKey(),
                        'image_path' => '',
                        'thumbnail_path' => null,
                        'title' => null,
                        'alt_text' => null,
                        'sort_order' => $nextSortOrder++,
                        'is_primary' => false,
                        'status' => 'active',
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    $paths = $this->imageVariantService->store(
                        $file,
                        'product',
                        $this->imageDirectory($product, $image),
                        $this->imageFilenamePrefix($product, $image),
                    );
                    $image->forceFill([
                        'image_path' => $paths['web'] ?? array_values($paths)[0],
                        'thumbnail_path' => $paths['thumb'] ?? array_values($paths)[0],
                    ])->save();

                    if ($group['attribute_value_id'] !== null) {
                        $image->attributeValues()->sync([$group['attribute_value_id']]);
                    }

                    if ($product->primary_image_id === null) {
                        $this->setPrimaryImage($product, $image, $actorId);
                    }
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

            $this->assertCurrentImageLimits($product);

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
            $this->forceDeleteImage($image, $actorId);

            $this->refreshPrimaryImage($product, $actorId);
        });
    }

    /**
     * @param array<int, int> $imageIds
     */
    public function deleteMany(Product $product, array $imageIds, int $actorId): int
    {
        if ($imageIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($product, $imageIds, $actorId): int {
            $images = ProductImage::query()
                ->where('product_id', $product->getKey())
                ->whereIn('id', $imageIds)
                ->get();

            foreach ($images as $image) {
                $this->forceDeleteImage($image, $actorId);
            }

            $this->refreshPrimaryImage($product, $actorId);

            return $images->count();
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

    private function forceDeleteImage(ProductImage $image, int $actorId): void
    {
        $paths = collect([$image->image_path, $image->thumbnail_path, ...$this->variantPathsForImage($image)])
            ->filter()
            ->unique()
            ->values();

        $image->forceFill([
            'deleted_by' => $actorId,
            'updated_by' => $actorId,
        ])->save();

        $image->forceDelete();

        Storage::disk('public')->delete($paths->all());
    }

    /**
     * @return array<int, string>
     */
    private function variantPathsForImage(ProductImage $image): array
    {
        if (! $image->image_path) {
            return [];
        }

        $directory = dirname($image->image_path);
        $prefix = "p{$image->product_id}-img{$image->getKey()}-";

        return collect(Storage::disk('public')->files($directory))
            ->filter(fn (string $path): bool => str_starts_with(basename($path), $prefix))
            ->values()
            ->all();
    }

    private function imageDirectory(Product $product, ProductImage $image): string
    {
        return "products/{$product->getKey()}-{$product->uuid}/images";
    }

    private function imageFilenamePrefix(Product $product, ProductImage $image): string
    {
        return "p{$product->getKey()}-img{$image->getKey()}";
    }

    private function assertCurrentImageLimits(Product $product): void
    {
        $limits = $this->imageLimits($product);
        $activeImages = ProductImage::query()
            ->with('attributeValues')
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->get();

        if ($activeImages->count() > $limits['total']) {
            throw ValidationException::withMessages([
                'images' => "A product can have a maximum of {$limits['total']} active images.",
            ]);
        }

        $entireCount = $activeImages->filter(fn (ProductImage $image): bool => $image->attributeValues->isEmpty())->count();
        $entireLimit = $limits['has_primary_variant'] ? $limits['entire_product'] : $limits['total'];

        if ($entireCount > $entireLimit) {
            throw ValidationException::withMessages([
                'images' => "A product can have a maximum of {$entireLimit} entire product images.",
            ]);
        }

        if (! $limits['has_primary_variant']) {
            return;
        }

        foreach ($limits['value_ids'] as $valueId) {
            $count = $activeImages
                ->filter(fn (ProductImage $image): bool => $image->attributeValues->pluck('id')->contains($valueId))
                ->count();

            if ($count > $limits['per_variant_value']) {
                throw ValidationException::withMessages([
                    'images' => "A product can have a maximum of {$limits['per_variant_value']} images per {$limits['attribute_label']} value.",
                ]);
            }
        }
    }

    /**
     * @param array<int, array{attribute_value_id: int|null, files: array<int, UploadedFile>}> $groups
     */
    private function assertCanAddUploadGroups(Product $product, array $groups): void
    {
        $limits = $this->imageLimits($product);
        $newTotal = collect($groups)->sum(fn (array $group): int => count($group['files']));
        $activeCount = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->count();

        if ($activeCount + $newTotal > $limits['total']) {
            throw ValidationException::withMessages([
                'images' => "A product can have a maximum of {$limits['total']} active images.",
            ]);
        }

        $newEntireCount = collect($groups)
            ->filter(fn (array $group): bool => $group['attribute_value_id'] === null)
            ->sum(fn (array $group): int => count($group['files']));
        $entireLimit = $limits['has_primary_variant'] ? $limits['entire_product'] : $limits['total'];
        $activeEntireCount = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->where('status', 'active')
            ->whereDoesntHave('attributeValues')
            ->count();

        if ($activeEntireCount + $newEntireCount > $entireLimit) {
            throw ValidationException::withMessages([
                'images' => "A product can have a maximum of {$entireLimit} entire product images.",
            ]);
        }

        if (! $limits['has_primary_variant']) {
            return;
        }

        foreach ($limits['value_ids'] as $valueId) {
            $newValueCount = collect($groups)
                ->filter(fn (array $group): bool => (int) $group['attribute_value_id'] === (int) $valueId)
                ->sum(fn (array $group): int => count($group['files']));

            if ($newValueCount === 0) {
                continue;
            }

            $activeValueCount = ProductImage::query()
                ->where('product_id', $product->getKey())
                ->where('status', 'active')
                ->whereHas('attributeValues', fn ($query) => $query->whereKey($valueId))
                ->count();

            if ($activeValueCount + $newValueCount > $limits['per_variant_value']) {
                throw ValidationException::withMessages([
                    'images' => "A product can have a maximum of {$limits['per_variant_value']} images per {$limits['attribute_label']} value.",
                ]);
            }
        }
    }

    /**
     * @return array{has_primary_variant: bool, total: int, per_variant_value: int, entire_product: int, value_ids: array<int, int>, attribute_label: string}
     */
    public function imageLimits(Product $product): array
    {
        $mapping = $this->imageAttributeMapping($product);
        $valueIds = $this->selectableImageAttributeValues($product)
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
        $hasPrimaryVariant = $mapping !== null && $valueIds !== [];
        $noVariantMax = max(1, (int) config('products.images.no_variant_max', 8));
        $perVariantValue = max(1, (int) config('products.images.per_variant_value', 2));
        $entireProduct = max(0, (int) config('products.images.entire_product', 2));

        return [
            'has_primary_variant' => $hasPrimaryVariant,
            'total' => $hasPrimaryVariant ? (count($valueIds) * $perVariantValue) + $entireProduct : $noVariantMax,
            'per_variant_value' => $perVariantValue,
            'entire_product' => $entireProduct,
            'value_ids' => $valueIds,
            'attribute_label' => $mapping?->group?->name ?? 'primary variant',
        ];
    }

    /**
     * @param array<int|string, array<int, UploadedFile>> $groups
     * @return array<int, array{attribute_value_id: int|null, files: array<int, UploadedFile>}>
     */
    private function normalizeUploadGroups(Product $product, array $groups): array
    {
        $normalized = [];

        foreach ($groups as $attributeValueId => $files) {
            if (! is_array($files) || $files === []) {
                continue;
            }

            $resolvedAttributeValueId = is_numeric($attributeValueId) && (int) $attributeValueId > 0
                ? (int) $attributeValueId
                : null;

            $this->assertValidAttributeValue($product, $resolvedAttributeValueId);

            $normalized[] = [
                'attribute_value_id' => $resolvedAttributeValueId,
                'files' => array_values($files),
            ];
        }

        return $normalized;
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
