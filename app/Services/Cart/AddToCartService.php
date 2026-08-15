<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\ProductAvailability\ProductAvailabilityResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddToCartService
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly ProductAvailabilityResolver $availabilityResolver,
    ) {
    }

    /**
     * @return array{cart_count: string}
     */
    public function add(Request $request, int $variantId, string $quantity): array
    {
        $quantity = $this->normalizeQuantity($quantity);

        return $this->retryOnDuplicate(function () use ($request, $variantId, $quantity): array {
            return DB::transaction(function () use ($request, $variantId, $quantity): array {
                $cart = $this->cartResolver->resolve($request);
                $variant = $this->variantForCart($variantId);
                $product = $variant->product;
                $shop = $variant->shop;
                $cartItem = CartItem::query()
                    ->where('cart_id', $cart->getKey())
                    ->where('product_variant_id', $variant->getKey())
                    ->lockForUpdate()
                    ->first();
                $existingQuantity = $cartItem instanceof CartItem ? (float) $cartItem->quantity : 0.0;
                $finalQuantity = $existingQuantity + (float) $quantity;

                $this->validateQuantityRules($variant, $quantity, $finalQuantity);
                $this->validateStock($variant, $finalQuantity);

                if ($cartItem instanceof CartItem) {
                    $cartItem->forceFill([
                        'quantity' => $this->decimal($finalQuantity, 3),
                        'unit_price' => $variant->selling_price,
                    ])->save();
                } else {
                    CartItem::query()->create([
                        'cart_id' => $cart->getKey(),
                        'shop_id' => $shop->getKey(),
                        'product_id' => $product->getKey(),
                        'product_variant_id' => $variant->getKey(),
                        'quantity' => $this->decimal((float) $quantity, 3),
                        'unit_price' => $variant->selling_price,
                    ]);
                }

                return [
                    'cart_count' => $this->cartResolver->itemCount($request),
                ];
            });
        });
    }

    private function variantForCart(int $variantId): ProductVariant
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

        $product = $variant->product;
        $shop = $variant->shop;

        if ($product->deleted_at !== null || $product->status !== 'active') {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This product is currently unavailable.',
            ]);
        }

        if ($shop->deleted_at !== null || $shop->status !== 'active') {
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

        return $variant;
    }

    private function normalizeQuantity(string $quantity): string
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

    private function validateQuantityRules(ProductVariant $variant, string $requestedQuantity, float $finalQuantity): void
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

    private function validateStock(ProductVariant $variant, float $finalQuantity): void
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

    private function decimal(float $value, int $places): string
    {
        return number_format($value, $places, '.', '');
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function retryOnDuplicate(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? '');
            $driverCode = (string) ($exception->errorInfo[1] ?? '');

            if ($sqlState === '23000' || $driverCode === '1062' || $driverCode === '19') {
                return $callback();
            }

            throw $exception;
        }
    }
}
