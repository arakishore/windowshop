<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\Admin\AdminSettingsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProductListingService
{
    public const PER_PAGE = 12;

    private const FALLBACK_IMAGE = 'assets/storefront/images/no-image-icon.png';

    public function __construct(
        private readonly AdminSettingsService $settings,
    ) {
    }

    public function marketplaceProducts(int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->storefrontQuery()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->cardData($product));
    }

    public function categoryProducts(ProductCategory $category, array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $filters = $this->sanitizeListingFilters($filters);

        return $this->storefrontQuery($this->categoryIds($category), $filters)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->cardData($product));
    }

    /**
     * @return Collection<int, ProductCategoryAttributeGroup>
     */
    public function categoryAttributeFilters(ProductCategory $category): Collection
    {
        return ProductCategoryAttributeGroup::query()
            ->where('root_product_category_id', $category->rootCategoryId())
            ->with(['group' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['values' => fn ($query) => $query->where('status', 'active')])])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductCategoryAttributeGroup $mapping): bool => $mapping->group !== null && $mapping->group->values->isNotEmpty())
            ->values();
    }

    /**
     * @param array<int, int>|null $categoryIds
     * @param array{attributes: array<int, array<int, int>>, price_min: float|null, price_max: float|null, discount_min: int|null} $filters
     */
    private function storefrontQuery(?array $categoryIds = null, array $filters = []): Builder
    {
        $now = now();
        $defaultVariantId = ProductVariant::query()
            ->select('id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('status', 'active')
            ->where('is_sellable', true)
            ->where('is_default', true)
            ->where('mrp', '>', 0)
            ->where('selling_price', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(1);

        return Product::query()
            ->select('products.*')
            ->selectSub($defaultVariantId, 'storefront_variant_id')
            ->with([
                'brand:id,name',
                'primaryImage' => fn ($query) => $query
                    ->where('status', 'active')
                    ->select('id', 'product_id', 'image_path', 'thumbnail_path', 'alt_text', 'status'),
                'storefrontCardVariant:id,product_id,mrp,selling_price,is_default,status,is_sellable',
            ])
            ->where('products.status', 'active')
            ->when($categoryIds !== null, fn (Builder $query) => $query->whereIn('products.product_category_id', $categoryIds))
            ->when(($filters['attributes'] ?? []) !== [], fn (Builder $query) => $this->applyAttributeFilters($query, $filters['attributes']))
            ->when($this->hasPriceFilters($filters), fn (Builder $query) => $this->applyPriceFilters($query, $filters))
            ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('shop', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereColumn('shops.merchant_id', 'products.merchant_id')
                ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active')))
            ->whereExists($defaultVariantId)
            ->orderByRaw(
                'CASE WHEN products.is_featured = 1 AND (products.featured_from IS NULL OR products.featured_from <= ?) AND (products.featured_until IS NULL OR products.featured_until >= ?) THEN 0 ELSE 1 END',
                [$now, $now],
            )
            ->orderBy('products.sort_order')
            ->orderByDesc('products.created_at')
            ->orderBy('products.id');
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{attributes: array<int, array<int, int>>, price_min: float|null, price_max: float|null, discount_min: int|null}
     */
    private function sanitizeListingFilters(array $filters): array
    {
        $discounts = collect((array) ($filters['discount_min'] ?? []))
            ->map(fn ($value) => filter_var($value, FILTER_VALIDATE_INT))
            ->filter(fn ($value): bool => in_array($value, [30, 40, 50, 60, 70], true));

        return [
            'attributes' => $this->sanitizeAttributeFilters((array) ($filters['attributes'] ?? [])),
            'price_min' => $this->sanitizePrice($filters['price_min'] ?? null),
            'price_max' => $this->sanitizePrice($filters['price_max'] ?? null),
            'discount_min' => $discounts->isEmpty() ? null : (int) $discounts->max(),
        ];
    }

    /**
     * @param array<mixed> $attributeFilters
     * @return array<int, array<int, int>>
     */
    private function sanitizeAttributeFilters(array $attributeFilters): array
    {
        return collect($attributeFilters)
            ->mapWithKeys(function ($values, $groupId): array {
                $groupId = filter_var($groupId, FILTER_VALIDATE_INT);

                if (! $groupId) {
                    return [];
                }

                $valueIds = collect((array) $values)
                    ->map(fn ($valueId) => filter_var($valueId, FILTER_VALIDATE_INT))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return $valueIds === [] ? [] : [(int) $groupId => $valueIds];
            })
            ->all();
    }

    private function sanitizePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $price = filter_var($value, FILTER_VALIDATE_FLOAT);

        return $price === false || $price < 0 ? null : (float) $price;
    }

    /**
     * @param array<int, array<int, int>> $attributeFilters
     */
    private function applyAttributeFilters(Builder $query, array $attributeFilters): void
    {
        foreach ($attributeFilters as $groupId => $valueIds) {
            $query->where(function (Builder $query) use ($groupId, $valueIds): void {
                $query->whereHas('attributes', fn (Builder $query) => $query
                    ->where('product_attribute_group_id', $groupId)
                    ->whereIn('product_attribute_group_value_id', $valueIds))
                    ->orWhereHas('variants', fn (Builder $query) => $query
                        ->where('status', 'active')
                        ->where('is_sellable', true)
                        ->whereHas('attributes', fn (Builder $query) => $query
                            ->where('product_attribute_group_id', $groupId)
                            ->whereIn('product_attribute_group_value_id', $valueIds)));
            });
        }
    }

    /**
     * @param array{price_min: float|null, price_max: float|null, discount_min: int|null} $filters
     */
    private function hasPriceFilters(array $filters): bool
    {
        return ($filters['price_min'] ?? null) !== null
            || ($filters['price_max'] ?? null) !== null
            || ($filters['discount_min'] ?? null) !== null;
    }

    /**
     * @param array{price_min: float|null, price_max: float|null, discount_min: int|null} $filters
     */
    private function applyPriceFilters(Builder $query, array $filters): void
    {
        $query->whereHas('variants', function (Builder $query) use ($filters): void {
            $query->where('status', 'active')
                ->where('is_sellable', true)
                ->where('is_default', true)
                ->where('mrp', '>', 0)
                ->where('selling_price', '>', 0)
                ->when($filters['price_min'] !== null, fn (Builder $query) => $query->where('selling_price', '>=', $filters['price_min']))
                ->when($filters['price_max'] !== null, fn (Builder $query) => $query->where('selling_price', '<=', $filters['price_max']))
                ->when($filters['discount_min'] !== null, fn (Builder $query) => $query->whereRaw(
                    '((mrp - selling_price) * 100 / mrp) >= ?',
                    [$filters['discount_min']],
                ));
        });
    }

    /**
     * @return array<int, int>
     */
    private function categoryIds(ProductCategory $category): array
    {
        return ProductCategory::query()
            ->where(function (Builder $query) use ($category): void {
                $query->where('id', $category->getKey())
                    ->orWhere('parent_id', $category->getKey())
                    ->orWhereHas('parent', fn (Builder $query) => $query->where('parent_id', $category->getKey()));
            })
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function cardData(Product $product): array
    {
        $variant = $product->storefrontCardVariant;
        $sellingPrice = (float) $variant->selling_price;
        $mrp = (float) $variant->mrp;
        $hasDiscount = $mrp > $sellingPrice;
        $discountPercent = $hasDiscount ? (int) round((($mrp - $sellingPrice) / $mrp) * 100) : 0;
        $image = $this->imageUrl($product);

        return [
            'name' => $product->product_name,
            'brand' => $product->brand?->name,
            'url' => route('storefront.product.detail'),
            'image' => $image,
            'hover_image' => $image,
            'price' => $this->money($sellingPrice),
            'old_price' => $hasDiscount ? $this->money($mrp) : null,
            'badge' => $discountPercent > 0 ? '-'.$discountPercent.'%' : null,
            'badge_class' => $discountPercent > 0 ? 'sale' : null,
            'description' => $product->short_description ?: 'A local shop product listing with clean catalogue-ready details.',
            'show_rating' => false,
            'swatches' => [],
        ];
    }

    private function imageUrl(Product $product): string
    {
        $path = $product->primaryImage?->thumbnail_path ?: $product->primaryImage?->image_path;

        if ($path && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset(self::FALLBACK_IMAGE);
    }

    private function money(float $value): string
    {
        $currency = $this->settings->currencyConfig();
        $amount = number_format(
            $value,
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$amount
            : $amount.' '.$symbol;
    }
}
