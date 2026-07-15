<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductPurgeService
{
    public function purge(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product = Product::withTrashed()
                ->with(['images' => fn ($query) => $query->withTrashed()])
                ->whereKey($product->getKey())
                ->firstOrFail();

            foreach ($product->images as $image) {
                Storage::disk('public')->delete($this->imagePaths($image));
            }

            Storage::disk('public')->deleteDirectory("products/{$product->getKey()}-{$product->uuid}/images");

            $product->forceFill(['primary_image_id' => null])->saveQuietly();
            $product->forceDelete();
        });
    }

    /**
     * @return array<int, string>
     */
    private function imagePaths(ProductImage $image): array
    {
        $paths = collect([$image->image_path, $image->thumbnail_path]);

        if ($image->image_path) {
            $directory = dirname($image->image_path);
            $prefix = "p{$image->product_id}-img{$image->getKey()}-";

            $paths = $paths->merge(
                collect(Storage::disk('public')->files($directory))
                    ->filter(fn (string $path): bool => str_starts_with(basename($path), $prefix)),
            );
        }

        return $paths
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
