<?php

namespace App\Services\Merchant;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Services\Product\ProductImageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PosProductSearchService
{
    public function __construct(
        private readonly ProductImageService $productImageService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function search(Shop $shop, string $query, bool $scannerMode = false): array
    {
        $query = $this->normalizeQuery($query);

        if ($query === '') {
            return $this->none('Enter a barcode, SKU, or product name.');
        }

        $barcodeMatch = $this->exactVariantMatch($shop, 'barcode', $query);
        if ($barcodeMatch['status'] !== 'none') {
            return $barcodeMatch;
        }

        $skuMatch = $this->exactVariantMatch($shop, 'sku', $query);
        if ($skuMatch['status'] !== 'none') {
            return $skuMatch;
        }

        if ($scannerMode) {
            return $this->none("No product found for barcode: {$query}");
        }

        return [
            'match_type' => 'text',
            'exact_match' => false,
            'auto_add' => false,
            'items' => $this->textResults($shop, $query)->map(fn (ProductVariant $variant): array => $this->itemPayload($variant))->values(),
        ];
    }

    private function normalizeQuery(string $query): string
    {
        return trim(str_replace(["\r", "\n"], '', $query));
    }

    /**
     * @return array<string, mixed>
     */
    private function exactVariantMatch(Shop $shop, string $field, string $query): array
    {
        /** @var Collection<int, ProductVariant> $matches */
        $matches = ProductVariant::query()
            ->with(['product', 'attributes.group', 'attributes.value'])
            ->where($field, $query)
            ->where('status', 'active')
            ->whereHas('product', function ($productQuery) use ($shop): void {
                $productQuery
                    ->where('merchant_id', $shop->merchant_id)
                    ->where('shop_id', $shop->getKey())
                    ->where('status', 'active');
            })
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            Log::warning('Duplicate POS barcode/SKU match detected.', [
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
                'field' => $field,
                'query' => $query,
                'variant_ids' => $matches->pluck('id')->all(),
            ]);

            return [
                'status' => 'conflict',
                'match_type' => $field,
                'exact_match' => false,
                'auto_add' => false,
                'items' => [],
                'message' => 'This barcode is assigned to multiple variants. Please correct the product data.',
            ];
        }

        $variant = $matches->first();

        if (! $variant instanceof ProductVariant) {
            return ['status' => 'none'];
        }

        return [
            'status' => 'matched',
            'match_type' => $field,
            'exact_match' => true,
            'auto_add' => true,
            'item' => $this->itemPayload($variant),
        ];
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function textResults(Shop $shop, string $query): Collection
    {
        return ProductVariant::query()
            ->with(['product', 'attributes.group', 'attributes.value'])
            ->where('status', 'active')
            ->whereHas('product', function ($productQuery) use ($shop): void {
                $productQuery
                    ->where('merchant_id', $shop->merchant_id)
                    ->where('shop_id', $shop->getKey())
                    ->where('status', 'active');
            })
            ->where(function ($variantQuery) use ($query): void {
                $variantQuery
                    ->where('name', 'like', "%{$query}%")
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('product_name', 'like', "%{$query}%"))
                    ->orWhereHas('attributes.value', fn ($valueQuery) => $valueQuery->where('name', 'like', "%{$query}%"));
            })
            ->limit(30)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function none(string $message): array
    {
        return [
            'match_type' => 'none',
            'exact_match' => false,
            'auto_add' => false,
            'items' => [],
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(ProductVariant $variant): array
    {
        /** @var Product $product */
        $product = $variant->product;
        $attributeSearch = $variant->attributes
            ->flatMap(fn ($attribute): array => [
                $attribute->group?->name,
                $attribute->value?->name,
                $attribute->value?->code,
            ])
            ->filter()
            ->implode(' ');
        $imagePath = $this->productImageService
            ->galleryForVariant($product, $variant)
            ->first()
            ?->image_path;

        return [
            'id' => $variant->getKey(),
            'product_id' => $product->getKey(),
            'variant_id' => $variant->getKey(),
            'product_name' => $product->product_name,
            'name' => $product->product_name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => (float) $variant->selling_price,
            'selling_price' => $variant->selling_price,
            'mrp' => $variant->mrp,
            'stock' => (int) $variant->stock_quantity,
            'image_url' => $imagePath ? Storage::disk('public')->url($imagePath) : null,
            'category_id' => $product->product_category_id,
            'category_name' => $product->category?->name,
            'attribute_search' => $attributeSearch,
        ];
    }
}
