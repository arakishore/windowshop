<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Admin\AdminSettingsService;
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
            ->map(fn (CartItem $item): array => $this->itemData($item))
            ->groupBy('shop_id')
            ->map(function (Collection $shopItems): array {
                $first = $shopItems->first();
                $subtotalCents = (int) $shopItems->sum('line_subtotal_cents');

                return [
                    'shop_id' => $first['shop_id'],
                    'shop_name' => $first['shop_name'],
                    'subtotal_cents' => $subtotalCents,
                    'subtotal' => $this->moneyFromCents($subtotalCents),
                    'delivery_minimum' => $this->deliveryMinimumData((float) ($first['delivery_minimum_amount'] ?? 0), $subtotalCents),
                    'items' => $shopItems->values()->all(),
                ];
            })
            ->values();

        $subtotalCents = (int) $groups->sum('subtotal_cents');

        return [
            'is_empty' => false,
            'shop_groups' => $groups->all(),
            'subtotal_cents' => $subtotalCents,
            'subtotal' => $this->moneyFromCents($subtotalCents),
            'total_cents' => $subtotalCents,
            'total' => $this->moneyFromCents($subtotalCents),
        ];
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
            'subtotal_cents' => 0,
            'subtotal' => $this->moneyFromCents(0),
            'total_cents' => 0,
            'total' => $this->moneyFromCents(0),
        ];
    }
}
