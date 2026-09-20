<?php

namespace App\Services\Review;

use App\Models\ProductReview;
use App\Models\ProductReviewImage;
use App\Services\Image\ImageVariantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductReviewImageService
{
    public const MAX_IMAGES = 5;

    public function __construct(private readonly ImageVariantService $variants) {}

    /**
     * @param array<int, UploadedFile> $files
     * @return Collection<int, ProductReviewImage>
     */
    public function store(ProductReview $review, array $files, int $startingOrder = 0): Collection
    {
        $stored = collect();

        try {
            foreach (array_values($files) as $offset => $file) {
                $image = new ProductReviewImage([
                    'uuid' => (string) Str::uuid(),
                    'sort_order' => $startingOrder + $offset,
                ]);
                $image->setRelation('review', $review);
                $paths = $this->variants->store(
                    $file,
                    'product_review',
                    "product-reviews/{$review->uuid}/images/{$image->uuid}",
                );
                try {
                    $image->fill([
                        'image_path' => $paths['web'],
                        'thumbnail_path' => $paths['thumb'],
                    ]);
                    $review->images()->save($image);
                } catch (Throwable $exception) {
                    foreach ($paths as $path) {
                        Storage::disk('public')->delete($path);
                    }
                    throw $exception;
                }
                $stored->push($image);
            }
        } catch (Throwable $exception) {
            $stored->each(fn (ProductReviewImage $image) => $this->deleteFiles($image));
            throw $exception;
        }

        return $stored;
    }

    public function delete(ProductReviewImage $image): void
    {
        $this->deleteFiles($image);
        $image->delete();
    }

    public function deleteFiles(ProductReviewImage $image): void
    {
        foreach (array_filter([$image->image_path, $image->thumbnail_path]) as $path) {
            if ($this->ownedBy($image->review, $path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function ownedBy(ProductReview $review, string $path): bool
    {
        return str_starts_with($path, "product-reviews/{$review->uuid}/images/");
    }
}
