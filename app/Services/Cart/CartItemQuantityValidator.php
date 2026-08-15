<?php

namespace App\Services\Cart;

use App\Models\ProductVariant;
use App\Services\ProductAvailability\ProductAvailabilityResolver;
use Illuminate\Validation\ValidationException;

class CartItemQuantityValidator
{
    public function __construct(
        private readonly ProductAvailabilityResolver $availabilityResolver,
    ) {
    }

    public function variantForCart(int $variantId): ProductVariant
    {
        $variant = ProductVariant::query()
            ->with([
                'availabilityStatus',
                'product.availabilityStatus',
                'product.merchant',
                'product.shop.merchant',
                'shop.merchant',
            ])
            ->whereKey($variantId)
            ->where('status', 'active')
            ->where('is_sellable', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $variant instanceof ProductVariant || $variant->product === null || $variant->shop === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This variant is no longer available.',
            ]);
        }

        $this->ensureVariantCanBePurchased($variant);

        return $variant;
    }

    public function ensureVariantCanBePurchased(ProductVariant $variant): void
    {
        $variant->loadMissing([
            'availabilityStatus',
            'product.availabilityStatus',
            'product.merchant',
            'product.shop.merchant',
            'shop.merchant',
        ]);

        $product = $variant->product;
        $shop = $variant->shop;

        if ($variant->deleted_at !== null || $variant->status !== 'active' || ! $variant->is_sellable) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This variant is no longer available.',
            ]);
        }

        if ($product === null || $product->deleted_at !== null || $product->status !== 'active') {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This product is currently unavailable.',
            ]);
        }

        if ($shop === null || $shop->deleted_at !== null || $shop->status !== 'active') {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This shop is currently unavailable.',
            ]);
        }

        if ((int) $variant->product_id !== (int) $product->getKey()
            || (int) $variant->shop_id !== (int) $shop->getKey()
            || (int) $product->shop_id !== (int) $shop->getKey()
            || (int) $product->merchant_id !== (int) $shop->merchant_id
            || $product->merchant?->status !== 'active'
            || $shop->merchant?->status !== 'active') {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This product is currently unavailable.',
            ]);
        }

        if ((float) $variant->selling_price <= 0) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This product is currently unavailable.',
            ]);
        }
    }

    public function normalizeQuantity(string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Invalid quantity.',
            ]);
        }

        $quantity = (float) $quantity;

        if ($quantity <= 0 || $quantity > 999999999) {
            throw ValidationException::withMessages([
                'quantity' => 'Invalid quantity.',
            ]);
        }

        return $this->decimal($quantity, 3);
    }

    public function validateQuantityRules(ProductVariant $variant, string $requestedQuantity, float $finalQuantity): void
    {
        $requested = (float) $requestedQuantity;

        if (! $variant->allow_decimal_quantity && floor($requested) !== $requested) {
            throw ValidationException::withMessages([
                'quantity' => 'Please enter a whole number quantity.',
            ]);
        }

        $minimum = (float) ($variant->minimum_order_quantity ?: 1);
        $maximum = $variant->maximum_order_quantity !== null ? (float) $variant->maximum_order_quantity : null;
        $increment = (float) ($variant->quantity_increment ?: 1);

        if ($finalQuantity < $minimum) {
            throw ValidationException::withMessages([
                'quantity' => "Minimum quantity is {$this->decimal($minimum, 3)}.",
            ]);
        }

        if ($maximum !== null && $finalQuantity > $maximum) {
            throw ValidationException::withMessages([
                'quantity' => "Maximum quantity is {$this->decimal($maximum, 3)}.",
            ]);
        }

        if ($increment > 0 && fmod(round($finalQuantity, 3), $increment) > 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Invalid quantity increment.',
            ]);
        }
    }

    public function validateStock(ProductVariant $variant, float $finalQuantity): void
    {
        $availability = $this->availabilityResolver->resolve($variant);
        $stock = (float) $variant->getRawOriginal('stock_quantity');
        $purchaseAllowedWithoutStock = (bool) ($availability['purchase_allowed'] ?? false) || (bool) $variant->allow_backorder;

        if ($finalQuantity > $stock && ! $purchaseAllowedWithoutStock) {
            $available = max(0, $stock);

            throw ValidationException::withMessages([
                'quantity' => 'Only '.$this->decimal($available, 3).' items are currently available.',
            ]);
        }
    }

    public function decimal(float $value, int $places = 3): string
    {
        return number_format($value, $places, '.', '');
    }
}
