<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductBarcodeService
{
    public function generate(?Shop $shop = null): string
    {
        $prefix = $this->prefixForShop($shop);

        do {
            $barcode = $prefix.now()->format('ymd').str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->barcodeExists($barcode));

        return $barcode;
    }

    public function generateForVariant(ProductVariant $variant, ?User $actor = null): ProductVariant
    {
        if ($this->normalize($variant->barcode) !== null) {
            return $variant;
        }

        $variant->forceFill([
            'barcode' => $this->generate($variant->shop ?? $variant->product?->shop),
            'updated_by' => $actor?->getKey(),
        ])->save();

        return $variant->refresh();
    }

    public function generateMissingForProduct(Product $product, ?User $actor = null): int
    {
        return DB::transaction(function () use ($product, $actor): int {
            $count = 0;

            $product->variants()
                ->orderBy('id')
                ->get()
                ->each(function (ProductVariant $variant) use ($actor, &$count): void {
                    if ($this->normalize($variant->barcode) === null) {
                        $this->generateForVariant($variant, $actor);
                        $count++;
                    }
                });

            return $count;
        });
    }

    public function generateMissingForShop(Shop $shop, ?User $actor = null): int
    {
        return DB::transaction(function () use ($shop, $actor): int {
            $count = 0;

            ProductVariant::query()
                ->with(['product.shop', 'shop'])
                ->where('shop_id', $shop->getKey())
                ->where(function ($query): void {
                    $query->whereNull('barcode')
                        ->orWhere('barcode', '');
                })
                ->whereHas('product', fn ($query) => $query->where('shop_id', $shop->getKey()))
                ->orderBy('id')
                ->get()
                ->each(function (ProductVariant $variant) use ($actor, &$count): void {
                    $this->generateForVariant($variant, $actor);
                    $count++;
                });

            return $count;
        });
    }

    public function assertUnique(?string $barcode, ?int $ignoreVariantId = null): void
    {
        $barcode = $this->normalize($barcode);

        if ($barcode === null) {
            return;
        }

        $exists = ProductVariant::query()
            ->where('barcode', $barcode)
            ->when($ignoreVariantId !== null, fn ($query) => $query->whereKeyNot($ignoreVariantId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'variants' => "Barcode {$barcode} is already assigned to another variant.",
            ]);
        }
    }

    public function normalize(mixed $barcode): ?string
    {
        $barcode = trim(str_replace(["\r", "\n"], '', (string) ($barcode ?? '')));

        return $barcode === '' ? null : $barcode;
    }

    private function barcodeExists(string $barcode): bool
    {
        return ProductVariant::query()->where('barcode', $barcode)->exists();
    }

    private function prefixForShop(?Shop $shop): string
    {
        $name = trim((string) ($shop?->name ?? 'WindowShop'));
        $words = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $prefix = collect($words)
            ->take(3)
            ->map(fn (string $word): string => strtoupper(substr($word, 0, 1)))
            ->implode('');

        return $prefix !== '' ? $prefix : 'WS';
    }
}
