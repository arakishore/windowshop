<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartItemMutationService
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly CartItemQuantityValidator $quantityValidator,
        private readonly CartPageService $cartPage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, CartItem $cartItem, string $quantity): array
    {
        $quantity = $this->quantityValidator->normalizeQuantity($quantity);

        DB::transaction(function () use ($request, $cartItem, $quantity): void {
            $cart = $this->cartResolver->current($request);

            if ($cart === null) {
                abort(404);
            }

            $lockedItem = CartItem::query()
                ->whereKey($cartItem->getKey())
                ->where('cart_id', $cart->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedItem instanceof CartItem) {
                abort(404);
            }

            $lockedItem->load([
                'productVariant.availabilityStatus',
                'productVariant.product.availabilityStatus',
                'productVariant.product.merchant',
                'productVariant.product.shop.merchant',
                'productVariant.shop.merchant',
            ]);

            $variant = $lockedItem->productVariant;

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'This variant is no longer available.',
                ]);
            }

            $this->quantityValidator->ensureVariantCanBePurchased($variant);
            $this->quantityValidator->validateQuantityRules($variant, $quantity, (float) $quantity);
            $this->quantityValidator->validateStock($variant, (float) $quantity);

            $lockedItem->forceFill([
                'quantity' => $quantity,
                'unit_price' => $variant->selling_price,
            ])->save();
        });

        return $this->cartPage->payload($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(Request $request, CartItem $cartItem): array
    {
        DB::transaction(function () use ($request, $cartItem): void {
            $cart = $this->cartResolver->current($request);

            if ($cart === null) {
                abort(404);
            }

            $deleted = CartItem::query()
                ->whereKey($cartItem->getKey())
                ->where('cart_id', $cart->getKey())
                ->delete();

            abort_unless($deleted === 1, 404);
        });

        return $this->cartPage->payload($request);
    }
}
