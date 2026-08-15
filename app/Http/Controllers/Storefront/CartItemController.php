<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\Cart\AddToCartService;
use App\Services\Cart\CartItemMutationService;
use App\Services\Cart\CartPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartItemController extends Controller
{
    public function store(Request $request, AddToCartService $cart, CartPageService $cartPage): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric'],
        ]);

        try {
            $result = $cart->add(
                $request,
                (int) $data['product_variant_id'],
                (string) $data['quantity'],
            );
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()
                ->withErrors($exception->errors(), 'cart')
                ->withInput();
        }

        $payload = [
            'success' => true,
            'message' => 'Product added to cart.',
            'cart_count' => $result['cart_count'],
            ...$cartPage->pageData($request),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return back()->with('cart_success', $payload['message']);
    }

    public function update(Request $request, CartItem $cartItem, CartItemMutationService $cart): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric'],
        ]);

        try {
            $payload = $cart->update($request, $cartItem, (string) $data['quantity']);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()
                ->withErrors($exception->errors(), 'cart')
                ->withInput();
        }

        $payload = [
            'success' => true,
            'message' => 'Cart updated.',
            ...$payload,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('cart_success', $payload['message']);
    }

    public function destroy(Request $request, CartItem $cartItem, CartItemMutationService $cart): JsonResponse|RedirectResponse
    {
        $payload = [
            'success' => true,
            'message' => 'Item removed from cart.',
            ...$cart->remove($request, $cartItem),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('cart_success', $payload['message']);
    }
}
