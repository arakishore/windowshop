<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\Admin\AdminSettingsService;
use App\Services\System\SystemSettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProductListingService
{
    public const PER_PAGE = 12;

    private const FALLBACK_IMAGE = 'assets/storefront/images/no-image-icon.png';

    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly SystemSettingService $systemSettings,
        private readonly CustomerLocationService $location,
        private readonly ProductLocationSorter $locationSorter,
        private readonly StorefrontUrlService $urls,
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
     * @return array{product: array<string, mixed>, relatedProducts: Collection<int, array<string, mixed>>}|null
     */
    public function productDetail(?string $slug = null): ?array
    {
        $product = $this->storefrontProductDetailQuery()
            ->when($slug !== null, fn (Builder $query) => $query->where('products.slug', $slug))
            ->when($slug === null, fn (Builder $query) => $query
                ->orderBy('products.sort_order')
                ->orderByDesc('products.created_at')
                ->orderBy('products.id'))
            ->first();

        if (! $product instanceof Product) {
            return null;
        }

        return [
            'product' => $this->detailData($product),
            'relatedProducts' => $this->relatedProducts($product),
        ];
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

        $query = Product::query()
            ->select('products.*')
            ->selectSub($defaultVariantId, 'storefront_variant_id')
            ->leftJoin('shops as storefront_location_shops', function (JoinClause $join): void {
                $join->on('storefront_location_shops.id', '=', 'products.shop_id')
                    ->whereColumn('storefront_location_shops.merchant_id', 'products.merchant_id');
            })
            ->with([
                'brand:id,name',
                'category:id,parent_id,name,slug',
                'category.parent:id,parent_id,name,slug',
                'category.parent.parent:id,parent_id,name,slug',
                'shop:id,merchant_id,name,slug,status',
                'primaryImage' => fn ($query) => $query
                    ->where('status', 'active')
                    ->select('id', 'product_id', 'image_path', 'thumbnail_path', 'alt_text', 'status'),
                'storefrontCardVariant:id,product_id,mrp,selling_price,stock_quantity,allow_backorder,is_default,status,is_sellable',
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
            ->tap(fn (Builder $query) => $this->locationSorter->apply(
                $query,
                $this->location->postalCode(),
                'storefront_location_shops.pincode',
            ))
            ->orderByRaw(
                'CASE WHEN products.is_featured = 1 AND (products.featured_from IS NULL OR products.featured_from <= ?) AND (products.featured_until IS NULL OR products.featured_until >= ?) THEN 0 ELSE 1 END',
                [$now, $now],
            )
            ->orderBy('products.sort_order')
            ->orderByDesc('products.created_at')
            ->orderBy('products.id');

        return $query;
    }

    private function storefrontProductDetailQuery(): Builder
    {
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
                'category:id,name,slug,parent_id,product_disclaimer',
                'category.parent:id,name,slug,parent_id,product_disclaimer',
                'category.parent.parent:id,name,slug,parent_id,product_disclaimer',
                'rootProductCategory:id,name,slug,parent_id,product_disclaimer',
                'shop:id,merchant_id,name,slug,short_description,description,address_line_1,address_line_2,landmark,pincode,mobile,whatsapp_number,website_url,status',
                'shop.merchant:id,status',
                'availabilityStatus:id,name,customer_description,status',
                'primaryImage:id,product_id,image_path,thumbnail_path,alt_text,status',
                'attributes.group:id,name,code',
                'attributes.value:id,product_attribute_group_id,name,code,status',
                'images' => fn ($query) => $query
                    ->where('status', 'active')
                    ->select('id', 'product_id', 'image_path', 'thumbnail_path', 'alt_text', 'sort_order', 'status'),
                'storefrontCardVariant:id,product_id,shop_id,availability_status_id,sku,name,mrp,selling_price,stock_quantity,allow_backorder,is_default,status,is_sellable',
                'variants' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where('is_sellable', true)
                    ->select('id', 'product_id', 'shop_id', 'sku', 'name', 'mrp', 'selling_price', 'stock_quantity', 'allow_backorder', 'is_default', 'sort_order', 'status', 'is_sellable')
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'attributes.group:id,name,code',
                        'attributes.value:id,product_attribute_group_id,name,code,swatch_hex,status',
                    ]),
            ])
            ->where('products.status', 'active')
            ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('shop', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereColumn('shops.merchant_id', 'products.merchant_id')
                ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active')))
            ->whereExists($defaultVariantId);
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
    public function cardData(Product $product): array
    {
        $variant = $product->storefrontCardVariant;
        $sellingPrice = (float) $variant->selling_price;
        $mrp = (float) $variant->mrp;
        $hasDiscount = $mrp > $sellingPrice;
        $discountPercent = $hasDiscount ? (int) round((($mrp - $sellingPrice) / $mrp) * 100) : 0;
        $image = $this->imageUrl($product);

        return [
            'product_id' => (int) $product->getKey(),
            'name' => $product->product_name,
            'brand' => $product->brand?->name,
            'store' => $product->shop?->name ?? 'Local Store',
            'url' => $this->urls->product($product),
            'wishlist_store_url' => route('storefront.wishlist.products.store', $product),
            'wishlist_destroy_url' => route('storefront.wishlist.products.destroy', $product),
            'image' => $image,
            'hover_image' => $image,
            'price' => $this->money($sellingPrice),
            'selected_variant_id' => $variant->getKey(),
            'can_add_to_cart' => (float) $variant->stock_quantity > 0 || (bool) $variant->allow_backorder,
            'add_to_cart_url' => route('storefront.cart.items.store'),
            'old_price' => $hasDiscount ? $this->money($mrp) : null,
            'badge' => $discountPercent > 0 ? '-'.$discountPercent.'%' : null,
            'badge_class' => $discountPercent > 0 ? 'sale' : null,
            'description' => $product->short_description ?: 'A local shop product listing with clean catalogue-ready details.',
            'show_rating' => false,
            'swatches' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(Product $product): array
    {
        $variant = $product->storefrontCardVariant;
        $sellingPrice = (float) $variant->selling_price;
        $mrp = (float) $variant->mrp;
        $hasDiscount = $mrp > $sellingPrice;
        $discountPercent = $hasDiscount ? (int) round((($mrp - $sellingPrice) / $mrp) * 100) : 0;
        $images = $this->detailImages($product);
        $description = $product->description ?: ($product->short_description ?: 'A local shop product listing with clean catalogue-ready details.');

        return [
            'product_id' => (int) $product->getKey(),
            'name' => $product->product_name,
            'slug' => $product->slug,
            'wishlist_store_url' => route('storefront.wishlist.products.store', $product),
            'wishlist_destroy_url' => route('storefront.wishlist.products.destroy', $product),
            'delivery_check_url' => route('storefront.product.delivery-check', $product->slug),
            'category' => $product->category?->name ?? $product->rootProductCategory?->name ?? 'Products',
            'category_url' => $product->category ? $this->urls->category($product->category) : route('storefront.products'),
            'category_breadcrumbs' => $this->urls->categoryBreadcrumbs($product->category),
            'category_path' => $this->urls->categoryPath($product->category),
            'canonical_url' => $this->urls->product($product),
            'store' => $product->shop?->name ?? 'Local Store',
            'store_url' => $product->shop?->slug ? route('storefront.store.show', $product->shop->slug) : null,
            'store_address' => $this->shopAddress($product->shop),
            'store_whatsapp_url' => $this->whatsappUrl($product),
            'price' => $this->money($sellingPrice),
            'selected_variant_id' => $variant->getKey(),
            'can_add_to_cart' => (float) $variant->stock_quantity > 0 || (bool) $variant->allow_backorder,
            'add_to_cart_url' => route('storefront.cart.items.store'),
            'old_price' => $hasDiscount ? $this->money($mrp) : null,
            'discount' => $discountPercent > 0 ? '-'.$discountPercent.'%' : null,
            'sku' => $variant->sku ?: 'SKU-'.$variant->getKey(),
            'reviews' => '0 reviews',
            'sold_text' => 'Available from local shop',
            'viewing_text' => 'Check product details before visiting the store',
            'description' => $description,
            'short_description' => $product->short_description ?: Str::limit(strip_tags($description), 160),
            'meta_title' => $product->meta_title ?: $product->product_name,
            'meta_description' => $product->meta_description ?: Str::limit(strip_tags($product->short_description ?: $description), 160, ''),
            'images' => $images,
            'colors' => $this->colorSwatches($product),
            'sizes' => $this->sizeLabels($product),
            'size_guide' => $this->sizeGuide($product),
            'other_attributes' => $this->otherAttributes($product),
            'disclaimers' => $this->productDisclaimers($product),
            'availability' => $product->availabilityStatus?->name ?: ((float) $variant->stock_quantity > 0 ? 'In Stock' : 'Check with store'),
            'availability_note' => $product->availabilityStatus?->customer_description,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function relatedProducts(Product $product): Collection
    {
        return $this->storefrontQuery([$product->product_category_id])
            ->where('products.id', '!=', $product->getKey())
            ->limit(4)
            ->get()
            ->map(fn (Product $related): array => $this->cardData($related));
    }

    /**
     * @return array<int, string>
     */
    private function detailImages(Product $product): array
    {
        $images = $product->images
            ->map(fn ($image): string => $this->productImageUrl($image->image_path, $image->thumbnail_path))
            ->filter()
            ->values()
            ->all();

        return $images === [] ? [$this->imageUrl($product)] : $images;
    }

    /**
     * @return array<int, array{name: string, price: string, raw_price: float, variant_id: int}>
     */
    private function sizeLabels(Product $product): array
    {
        $sizes = $product->variants
            ->map(function (ProductVariant $variant): ?array {
                $size = $variant->attributes
                    ->first(fn ($attribute): bool => $attribute->group?->code === 'size' && $attribute->value !== null);

                if ($size === null) {
                    return null;
                }

                return [
                    'name' => $size->value->name,
                    'price' => $this->money((float) $variant->selling_price),
                    'raw_price' => (float) $variant->selling_price,
                    'variant_id' => $variant->getKey(),
                ];
            })
            ->filter()
            ->unique('name')
            ->values()
            ->all();

        if ($sizes !== []) {
            return $sizes;
        }

        if ($product->variants->count() <= 1) {
            return [];
        }

        $fallbackSizes = $product->variants
            ->map(function (ProductVariant $variant) use ($product): ?array {
                $label = $this->fallbackVariantSizeLabel($variant, $product);

                if ($label === null) {
                    return null;
                }

                return [
                    'name' => $label,
                    'price' => $this->money((float) $variant->selling_price),
                    'raw_price' => (float) $variant->selling_price,
                    'variant_id' => $variant->getKey(),
                ];
            })
            ->filter()
            ->unique('name')
            ->values()
            ->all();

        return count($fallbackSizes) > 1 ? $fallbackSizes : [];
    }

    private function fallbackVariantSizeLabel(ProductVariant $variant, Product $product): ?string
    {
        $label = trim((string) $variant->name);

        if ($label === '') {
            return null;
        }

        $normalizedLabel = Str::lower($label);
        $normalizedProductName = Str::lower(trim((string) $product->product_name));

        if ($normalizedLabel === 'default' || $normalizedLabel === $normalizedProductName) {
            return null;
        }

        return Str::length($label) <= 20 ? $label : null;
    }

    /**
     * @return array<int, array{name: string, hex: string|null}>
     */
    private function colorSwatches(Product $product): array
    {
        return $product->variants
            ->flatMap(fn (ProductVariant $variant) => $variant->attributes)
            ->filter(fn ($attribute): bool => $attribute->group?->code === 'color' && $attribute->value !== null)
            ->map(fn ($attribute): array => [
                'name' => $attribute->value->name,
                'hex' => $attribute->value->swatch_hex,
            ])
            ->unique('name')
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, subtitle: string, image: string, guide_key: string}|null
     */
    private function sizeGuide(Product $product): ?array
    {
        $root = $product->rootProductCategory?->name;
        $audience = $this->audienceCategoryName($product);
        $productType = $this->productTypeCategoryName($product);

        if ($root === null) {
            return null;
        }

        $guides = (array) config("storefront_size_guides.guides.{$root}", []);
        $filename = $this->sizeGuideFilename($guides, $audience, $productType);

        if ($filename === null) {
            return null;
        }

        $basePath = trim((string) config('storefront_size_guides.base_path', 'assets/storefront/images/size-guides'), '/');

        return [
            'title' => $this->sizeGuideTitle($audience, $productType, $root),
            'subtitle' => $this->sizeGuideSubtitle($audience, $productType),
            'image' => asset($basePath.'/'.$filename),
            'guide_key' => pathinfo($filename, PATHINFO_FILENAME),
        ];
    }

    private function audienceCategoryName(Product $product): string
    {
        $category = $product->category;
        $rootId = $product->rootProductCategory?->getKey();

        if ($category?->parent !== null && (int) $category->parent_id !== (int) $rootId) {
            return $category->parent->name;
        }

        if ($category !== null && (int) $category->getKey() !== (int) $rootId) {
            return $category->name;
        }

        return 'default';
    }

    private function productTypeCategoryName(Product $product): ?string
    {
        $category = $product->category;
        $rootId = $product->rootProductCategory?->getKey();

        if ($category === null || (int) $category->getKey() === (int) $rootId) {
            return null;
        }

        if ($category->parent !== null && (int) $category->parent_id !== (int) $rootId) {
            return $category->name;
        }

        return null;
    }

    private function sizeGuideFilename(array $guides, string $audience, ?string $productType): ?string
    {
        $audienceGuides = $guides[$audience] ?? [];

        if (is_array($audienceGuides)) {
            if ($productType !== null && isset($audienceGuides[$productType])) {
                return $audienceGuides[$productType];
            }

            if (isset($audienceGuides['default'])) {
                return $audienceGuides['default'];
            }
        }

        return is_string($guides['default'] ?? null) ? $guides['default'] : null;
    }

    private function sizeGuideTitle(string $audience, ?string $productType, string $root): string
    {
        if ($productType !== null) {
            return "{$audience} {$productType} Size Chart";
        }

        return $audience !== 'default' ? "{$audience} {$root} Size Chart" : "{$root} Size Chart";
    }

    private function sizeGuideSubtitle(string $audience, ?string $productType): string
    {
        return collect([$audience === 'default' ? null : $audience, $productType])
            ->filter()
            ->implode(' / ') ?: 'Size Guide';
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function otherAttributes(Product $product): array
    {
        return collect($product->attributes)
            ->merge($product->variants->flatMap(fn (ProductVariant $variant) => $variant->attributes))
            ->filter(fn ($attribute): bool => $attribute->group !== null
                && $attribute->value !== null
                && ! in_array($attribute->group->code, ['color', 'size'], true))
            ->groupBy(fn ($attribute): string => $attribute->group->name)
            ->map(fn (Collection $attributes, string $label): array => [
                'label' => $label,
                'value' => $attributes
                    ->map(fn ($attribute): string => $attribute->value->name)
                    ->unique()
                    ->implode(', '),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function productDisclaimers(Product $product): array
    {
        $categoryDisclaimer = collect([
            $product->category?->product_disclaimer,
            $product->category?->parent?->product_disclaimer,
            $product->rootProductCategory?->product_disclaimer,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->first(fn (string $value): bool => $value !== '');

        return collect([
            $this->systemSettings->globalProductDisclaimer(),
            $categoryDisclaimer,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function shopAddress(?object $shop): ?string
    {
        if ($shop === null) {
            return null;
        }

        return collect([$shop->address_line_1, $shop->address_line_2, $shop->landmark, $shop->pincode])
            ->filter()
            ->implode(', ') ?: null;
    }

    private function whatsappUrl(Product $product): ?string
    {
        $shop = $product->shop;

        if ($shop === null) {
            return null;
        }

        $phone = $this->whatsappPhone($shop->whatsapp_number ?: $shop->mobile);

        if ($phone === null) {
            return null;
        }

        $shopName = $shop->name ?: 'your shop';
        $message = "Hello {$shopName}! I am interested in your {$product->product_name}.";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function whatsappPhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        $digits = ltrim($digits, '0');

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return strlen($digits) >= 11 ? $digits : null;
    }

    private function imageUrl(Product $product): string
    {
        $path = $product->primaryImage?->thumbnail_path ?: $product->primaryImage?->image_path;

        if ($path && Storage::disk('public')->exists($path)) {
            return $this->imageUrlFromPath($path);
        }

        return asset(self::FALLBACK_IMAGE);
    }

    private function imageUrlFromPath(?string $path): string
    {
        if ($path && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset(self::FALLBACK_IMAGE);
    }

    private function productImageUrl(?string $imagePath, ?string $thumbnailPath): string
    {
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            return asset('storage/'.$imagePath);
        }

        if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
            return asset('storage/'.$thumbnailPath);
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
