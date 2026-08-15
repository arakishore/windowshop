<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddToCartService
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly CartItemQuantityValidator $quantityValidator,
    ) {
    }

    /**
     * @return array{cart_count: string}
     */
    public function add(Request $request, int $variantId, string $quantity): array
    {
        $quantity = $this->quantityValidator->normalizeQuantity($quantity);

        return $this->retryOnDuplicate(function () use ($request, $variantId, $quantity): array {
            return DB::transaction(function () use ($request, $variantId, $quantity): array {
                $cart = $this->cartResolver->resolve($request);
                $variant = $this->quantityValidator->variantForCart($variantId);
                $product = $variant->product;
                $shop = $variant->shop;
                $cartItem = CartItem::query()
                    ->where('cart_id', $cart->getKey())
                    ->where('product_variant_id', $variant->getKey())
                    ->lockForUpdate()
                    ->first();
                $existingQuantity = $cartItem instanceof CartItem ? (float) $cartItem->quantity : 0.0;
                $finalQuantity = $existingQuantity + (float) $quantity;

                $this->quantityValidator->validateQuantityRules($variant, $quantity, $finalQuantity);
                $this->quantityValidator->validateStock($variant, $finalQuantity);

                if ($cartItem instanceof CartItem) {
                    $cartItem->forceFill([
                        'quantity' => $this->quantityValidator->decimal($finalQuantity),
                        'unit_price' => $variant->selling_price,
                    ])->save();
                } else {
                    CartItem::query()->create([
                        'cart_id' => $cart->getKey(),
                        'shop_id' => $shop->getKey(),
                        'product_id' => $product->getKey(),
                        'product_variant_id' => $variant->getKey(),
                        'quantity' => $this->quantityValidator->decimal((float) $quantity),
                        'unit_price' => $variant->selling_price,
                    ]);
                }

                return [
                    'cart_count' => $this->cartResolver->itemCount($request),
                ];
            });
        });
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
