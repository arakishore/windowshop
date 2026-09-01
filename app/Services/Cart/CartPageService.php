<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\Promotion\Engine\Data\GeneratedPromotionGift;
use App\Services\Promotion\Engine\PromotionCalculator;
use App\Services\Promotion\Engine\Data\PromotionCalculationResult;
use App\Services\Storefront\StorefrontProductPolicyPresenter;
use App\Services\Storefront\StorefrontUrlService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CartPageService
{
    private const FALLBACK_IMAGE = 'assets/storefront/images/no-image-icon.png';
    private const PAGE_DATA_ATTRIBUTE = 'storefront_cart_page_data';
    private const CURRENT_CART_ATTRIBUTE = 'storefront_current_cart';

    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly CartItemQuantityValidator $quantityValidator,
        private readonly AdminSettingsService $settings,
        private readonly StorefrontUrlService $urls,
        private readonly StorefrontProductPolicyPresenter $policyPresenter,
        private readonly PromotionCalculator $promotions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pageData(Request $request): array
    {
        if ($request->attributes->has(self::PAGE_DATA_ATTRIBUTE)) {
            return $request->attributes->get(self::PAGE_DATA_ATTRIBUTE);
        }

        $cart = $this->currentCart($request);

        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            $data = $this->emptyData();
            $request->attributes->set(self::PAGE_DATA_ATTRIBUTE, $data);

            return $data;
        }

        $data = $this->dataFromItems($cart->items);
        $request->attributes->set(self::PAGE_DATA_ATTRIBUTE, $data);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        return [
            ...$this->pageData($request),
            'cart_count' => $this->cartResolver->itemCount($request),
        ];
    }

    public function currentCart(Request $request): ?Cart
    {
        if ($request->attributes->has(self::CURRENT_CART_ATTRIBUTE)) {
            return $request->attributes->get(self::CURRENT_CART_ATTRIBUTE);
        }

        $cart = $this->cartResolver->current($request)?->load([
            'items' => fn ($query) => $query->orderBy('shop_id')->orderBy('id'),
            'items.shop.city',
            'items.shop.merchant',
            'items.product.primaryImage',
            'items.product.category.parent.parent',
            'items.product.merchant',
            'items.product.shop.merchant',
            'items.product.shop.settings',
            'items.product.returnPolicy',
            'items.productVariant.availabilityStatus',
            'items.productVariant.product.availabilityStatus',
            'items.productVariant.attributes.group',
            'items.productVariant.attributes.value',
        ]);

        $request->attributes->set(self::CURRENT_CART_ATTRIBUTE, $cart);

        return $cart;
    }

    /**
     * @param Collection<int, CartItem> $items
     * @return array<string, mixed>
     */
    private function dataFromItems(Collection $items): array
    {
        $groups = $items
            ->groupBy('shop_id')
            ->map(function (Collection $cartItems): array {
                $shopItems = $cartItems->map(fn (CartItem $item): array => $this->itemData($item));
                $first = $shopItems->first();
                $baseSubtotalCents = (int) $shopItems->sum('line_subtotal_cents');
                $promotionResult = $cartItems->first()?->shop instanceof Shop
                    ? $this->promotions->calculateForShop(
                        $cartItems->first()->shop,
                        $shopItems
                            ->filter(fn (array $item): bool => (bool) ($item['is_available'] ?? false))
                            ->map(fn (array $item): array => [
                                'product_variant_id' => (int) $item['product_variant_id'],
                                'quantity' => $item['quantity_value'],
                            ])
                            ->values()
                            ->all(),
                    )
                    : new PromotionCalculationResult((int) ($first['shop_id'] ?? 0), []);
                $shopItems = $this->applyPromotionsToItems($shopItems, $promotionResult);
                $giftItems = collect($promotionResult->generatedGifts)
                    ->map(fn (GeneratedPromotionGift $gift): array => $this->giftItemData($gift, (int) $first['shop_id'], (string) $first['shop_name']));
                $displayItems = $shopItems->concat($giftItems);
                $promotionDiscountCents = (int) $shopItems->sum('promotion_discount_cents');
                $giftDiscountCents = (int) $giftItems->sum('promotion_discount_cents');
                $subtotalCents = (int) $displayItems->sum('line_subtotal_cents');
                $baseSubtotalCents = (int) $displayItems->sum('base_line_subtotal_cents');

                return [
                    'shop_id' => $first['shop_id'],
                    'shop_name' => $first['shop_name'],
                    'base_subtotal_cents' => $baseSubtotalCents,
                    'base_subtotal' => $this->moneyFromCents($baseSubtotalCents),
                    'promotion_discount_cents' => $promotionDiscountCents + $giftDiscountCents,
                    'promotion_discount' => ($promotionDiscountCents + $giftDiscountCents) > 0 ? '-'.$this->moneyFromCents($promotionDiscountCents + $giftDiscountCents) : $this->moneyFromCents(0),
                    'subtotal_cents' => $subtotalCents,
                    'subtotal' => $this->moneyFromCents($subtotalCents),
                    'applied_promotions' => $promotionResult->appliedPromotions(),
                    'delivery_minimum' => $this->deliveryMinimumData((float) ($first['delivery_minimum_amount'] ?? 0), $subtotalCents),
                    'items' => $displayItems->values()->all(),
                ];
            })
            ->values();

        $baseSubtotalCents = (int) $groups->sum('base_subtotal_cents');
        $promotionDiscountCents = (int) $groups->sum('promotion_discount_cents');
        $subtotalCents = (int) $groups->sum('subtotal_cents');

        return [
            'is_empty' => false,
            'shop_groups' => $groups->all(),
            'base_subtotal_cents' => $baseSubtotalCents,
            'base_subtotal' => $this->moneyFromCents($baseSubtotalCents),
            'promotion_discount_cents' => $promotionDiscountCents,
            'promotion_discount' => $promotionDiscountCents > 0 ? '-'.$this->moneyFromCents($promotionDiscountCents) : $this->moneyFromCents(0),
            'discount' => $promotionDiscountCents > 0 ? '-'.$this->moneyFromCents($promotionDiscountCents) : 'None',
            'applied_promotions' => $groups->flatMap(fn (array $group): array => $group['applied_promotions'] ?? [])->values()->all(),
            'subtotal_cents' => $subtotalCents,
            'subtotal' => $this->moneyFromCents($subtotalCents),
            'total_cents' => $subtotalCents,
            'total' => $this->moneyFromCents($subtotalCents),
        ];
    }

    private function applyPromotionsToItems(Collection $shopItems, PromotionCalculationResult $promotionResult): Collection
    {
        return $shopItems->map(function (array $item) use ($promotionResult): array {
            $adjustment = $promotionResult->line((int) ($item['product_variant_id'] ?? 0));
            $baseLineSubtotalCents = (int) $item['line_subtotal_cents'];
            $promotionDiscountCents = $adjustment?->promotionDiscountCents ?? 0;
            $finalLineSubtotalCents = max(0, $baseLineSubtotalCents - $promotionDiscountCents);

            return [
                ...$item,
                'base_line_subtotal_cents' => $baseLineSubtotalCents,
                'base_line_subtotal' => $this->moneyFromCents($baseLineSubtotalCents),
                'promotion_discount_cents' => $promotionDiscountCents,
                'promotion_discount' => $promotionDiscountCents > 0 ? '-'.$this->moneyFromCents($promotionDiscountCents) : $this->moneyFromCents(0),
                'promotion' => $adjustment?->winningPromotion?->toMetadata(),
                'line_subtotal_cents' => $finalLineSubtotalCents,
                'line_subtotal' => $this->moneyFromCents($finalLineSubtotalCents),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(CartItem $item): array
    {
        $variant = $item->productVariant;
        $product = $item->product;
        $isPurchasable = $variant instanceof ProductVariant;
        $message = null;

        if ($isPurchasable) {
            try {
                $this->quantityValidator->ensureVariantCanBePurchased($variant);
                $this->quantityValidator->validateStock($variant, (float) $item->quantity);
                $decision = $this->quantityValidator->availabilityDecision($variant, (float) $item->quantity);
                $message = (bool) ($decision['allowed'] ?? false) ? ($decision['message'] ?? null) : null;
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $isPurchasable = false;
                $message = collect($exception->errors())->flatten()->first() ?: 'Currently unavailable.';
            }
        } else {
            $message = 'Currently unavailable.';
        }

        $currentPrice = $isPurchasable ? (string) $variant->selling_price : (string) $item->unit_price;

        if ($isPurchasable && (string) $item->unit_price !== $currentPrice) {
            $item->forceFill(['unit_price' => $currentPrice])->save();
        }

        $quantity = (string) $item->quantity;
        $lineSubtotalCents = $this->lineSubtotalCents($quantity, $currentPrice);

        return [
            'id' => $item->getKey(),
            'shop_id' => $item->shop?->getKey() ?? 0,
            'shop_name' => $item->shop?->name ?: 'Unavailable Shop',
            'product_variant_id' => $variant?->getKey(),
            'product_name' => $product?->product_name ?: 'Unavailable Product',
            'product_url' => $product?->slug ? $this->urls->product($product) : '#',
            'return_exchange_policy' => $product instanceof Product
                ? $this->policyPresenter->returnExchange($product)
                : null,
            'delivery_minimum_amount' => $product instanceof Product
                ? $this->policyPresenter->deliveryMinimumAmount($product)
                : null,
            'image' => $this->imageUrl($item),
            'attributes' => $this->variantAttributes($variant),
            'quantity' => $this->formatQuantity($quantity),
            'quantity_value' => $quantity,
            'unit_price_cents' => $this->moneyToCents($currentPrice),
            'unit_price' => $this->moneyFromCents($this->moneyToCents($currentPrice)),
            'line_subtotal_cents' => $lineSubtotalCents,
            'line_subtotal' => $this->moneyFromCents($lineSubtotalCents),
            'update_url' => route('storefront.cart.items.update', $item),
            'remove_url' => route('storefront.cart.items.destroy', $item),
            'is_available' => $isPurchasable,
            'availability_message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function giftItemData(GeneratedPromotionGift $gift, int $shopId, string $shopName): array
    {
        return [
            'id' => 'gift-'.$gift->promotion->promotionId.'-'.$gift->variantId,
            'shop_id' => $shopId,
            'shop_name' => $shopName,
            'product_variant_id' => $gift->variantId,
            'product_name' => $gift->productName,
            'product_url' => '#',
            'return_exchange_policy' => null,
            'delivery_minimum_amount' => null,
            'image' => $this->giftImageUrl($gift),
            'attributes' => $gift->attributes,
            'quantity' => $gift->quantity,
            'quantity_value' => $gift->quantity,
            'unit_price_cents' => $this->moneyToCents($gift->unitPrice),
            'unit_price' => $this->moneyFromCents($this->moneyToCents($gift->unitPrice)),
            'base_line_subtotal_cents' => $gift->baseLineSubtotalCents,
            'base_line_subtotal' => $this->moneyFromCents($gift->baseLineSubtotalCents),
            'promotion_discount_cents' => $gift->promotionDiscountCents,
            'promotion_discount' => '-'.$this->moneyFromCents($gift->promotionDiscountCents),
            'promotion' => $gift->promotion->toMetadata(),
            'line_subtotal_cents' => $gift->finalLineSubtotalCents,
            'line_subtotal' => $this->moneyFromCents($gift->finalLineSubtotalCents),
            'update_url' => null,
            'remove_url' => null,
            'is_available' => true,
            'availability_message' => null,
            'is_generated_gift' => true,
        ];
    }

    private function giftImageUrl(GeneratedPromotionGift $gift): string
    {
        if ($gift->productImage && Storage::disk('public')->exists($gift->productImage)) {
            return asset('storage/'.$gift->productImage);
        }

        return asset(self::FALLBACK_IMAGE);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function variantAttributes(?ProductVariant $variant): array
    {
        if (! $variant instanceof ProductVariant) {
            return [];
        }

        return $variant->attributes
            ->filter(fn ($attribute): bool => $attribute->group !== null
                && $attribute->value !== null
                && $attribute->group->status === 'active'
                && $attribute->value->status === 'active')
            ->map(fn ($attribute): array => [
                'label' => $attribute->group->name,
                'value' => $attribute->value->name,
            ])
            ->values()
            ->all();
    }

    private function imageUrl(CartItem $item): string
    {
        $image = $item->product?->primaryImage;
        $path = $image?->thumbnail_path ?: $image?->image_path;

        if ($path && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset(self::FALLBACK_IMAGE);
    }

    private function lineSubtotalCents(string $quantity, string $unitPrice): int
    {
        return intdiv(($this->quantityToUnits($quantity) * $this->moneyToCents($unitPrice)) + 500, 1000);
    }

    /**
     * @return array{minimum_cents: int, remaining_cents: int, message: ?string}
     */
    private function deliveryMinimumData(float $minimum, int $subtotalCents): array
    {
        $minimumCents = $minimum > 0 ? $this->moneyToCents((string) $minimum) : 0;
        $remainingCents = max(0, $minimumCents - $subtotalCents);

        return [
            'minimum_cents' => $minimumCents,
            'remaining_cents' => $remainingCents,
            'message' => $remainingCents > 0
                ? 'Add '.$this->moneyFromCents($remainingCents).' more from this shop to qualify for delivery.'
                : null,
        ];
    }

    private function quantityToUnits(string $quantity): int
    {
        return (int) round(((float) $quantity) * 1000);
    }

    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function moneyFromCents(int $cents): string
    {
        $currency = $this->settings->currencyConfig();
        $amount = number_format(
            $cents / 100,
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$amount
            : $amount.' '.$symbol;
    }

    private function formatQuantity(string $quantity): string
    {
        return rtrim(rtrim($quantity, '0'), '.') ?: '0';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyData(): array
    {
        return [
            'is_empty' => true,
            'shop_groups' => [],
            'base_subtotal_cents' => 0,
            'base_subtotal' => $this->moneyFromCents(0),
            'promotion_discount_cents' => 0,
            'promotion_discount' => $this->moneyFromCents(0),
            'discount' => 'None',
            'applied_promotions' => [],
            'subtotal_cents' => 0,
            'subtotal' => $this->moneyFromCents(0),
            'total_cents' => 0,
            'total' => $this->moneyFromCents(0),
        ];
    }
}
