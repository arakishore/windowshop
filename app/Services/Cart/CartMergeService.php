<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartMergeService
{
    public function __construct(
        private readonly CartItemQuantityValidator $quantityValidator,
    ) {
    }

    public function mergeGuestTokenIntoCustomer(?string $guestToken, User $customer): void
    {
        if (! is_string($guestToken) || $guestToken === '') {
            return;
        }

        $this->retryOnDuplicate(function () use ($guestToken, $customer): void {
            DB::transaction(function () use ($guestToken, $customer): void {
                $guestCart = Cart::query()
                    ->where('session_token', $guestToken)
                    ->whereNull('user_id')
                    ->lockForUpdate()
                    ->first();

                if (! $guestCart instanceof Cart) {
                    return;
                }

                $guestCart->load('items');

                if ($guestCart->items->isEmpty()) {
                    $guestCart->delete();

                    return;
                }

                $customerCart = Cart::query()
                    ->where('user_id', $customer->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $customerCart instanceof Cart) {
                    $guestCart->forceFill([
                        'user_id' => $customer->getKey(),
                        'session_token' => null,
                    ])->save();

                    $this->revalidateCartItems($guestCart);

                    return;
                }

                foreach ($guestCart->items as $guestItem) {
                    $this->mergeItem($customerCart, $guestItem);
                }

                $guestCart->delete();
            });
        });
    }

    private function mergeItem(Cart $customerCart, CartItem $guestItem): void
    {
        try {
            $variant = $this->quantityValidator->variantForCart((int) $guestItem->product_variant_id);
            $quantity = $this->quantityValidator->normalizeQuantity((string) $guestItem->quantity);
        } catch (ValidationException) {
            $guestItem->delete();

            return;
        }

        $customerItem = CartItem::query()
            ->where('cart_id', $customerCart->getKey())
            ->where('product_variant_id', $variant->getKey())
            ->lockForUpdate()
            ->first();

        $existingQuantity = $customerItem instanceof CartItem ? (float) $customerItem->quantity : 0.0;
        $finalQuantity = $existingQuantity + (float) $quantity;

        try {
            $this->quantityValidator->validateQuantityRules($variant, $quantity, $finalQuantity);
            $this->quantityValidator->validateStock($variant, $finalQuantity);
        } catch (ValidationException) {
            $guestItem->delete();

            return;
        }

        if ($customerItem instanceof CartItem) {
            $customerItem->forceFill([
                'quantity' => $this->quantityValidator->decimal($finalQuantity),
                'unit_price' => $variant->selling_price,
            ])->save();
            $guestItem->delete();

            return;
        }

        $guestItem->forceFill([
            'cart_id' => $customerCart->getKey(),
            'shop_id' => $variant->shop_id,
            'product_id' => $variant->product_id,
            'unit_price' => $variant->selling_price,
        ])->save();
    }

    private function revalidateCartItems(Cart $cart): void
    {
        $cart->load('items');

        foreach ($cart->items as $item) {
            try {
                $variant = $this->quantityValidator->variantForCart((int) $item->product_variant_id);
                $quantity = $this->quantityValidator->normalizeQuantity((string) $item->quantity);
                $this->quantityValidator->validateQuantityRules($variant, $quantity, (float) $quantity);
                $this->quantityValidator->validateStock($variant, (float) $quantity);
                $item->forceFill([
                    'shop_id' => $variant->shop_id,
                    'product_id' => $variant->product_id,
                    'unit_price' => $variant->selling_price,
                ])->save();
            } catch (ValidationException) {
                $item->delete();
            }
        }
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
