<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\Admin\AdminSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CartPageService
{
    private const FALLBACK_IMAGE = 'assets/storefront/images/no-image-icon.png';

    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly CartItemQuantityValidator $quantityValidator,
        private readonly AdminSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pageData(Request $request): array
    {
        $cart = $this->currentCart($request);

        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            return $this->emptyData();
        }

        return $this->dataFromItems($cart->items);
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
        return $this->cartResolver->current($request)?->load([
            'items' => fn ($query) => $query->orderBy('shop_id')->orderBy('id'),
            'items.shop.city',
            'items.shop.merchant',
            'items.product.primaryImage',
            'items.product.merchant',
            'items.product.shop.merchant',
            'items.productVariant.availabilityStatus',
            'items.productVariant.product.availabilityStatus',
            'items.productVariant.attributes.group',
            'items.productVariant.attributes.value',
        ]);
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
            'product_name' => $product?->product_name ?: 'Unavailable Product',
            'product_url' => $product?->slug ? route('storefront.product.show', $product->slug) : '#',
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
