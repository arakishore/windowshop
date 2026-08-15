<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartResolver
{
    public const SESSION_TOKEN_KEY = 'storefront_cart_token';

    public function __construct(
        private readonly StorefrontCustomerContext $customerContext,
    ) {
    }

    public function resolve(Request $request): Cart
    {
        $storefrontUserId = $this->customerContext->userId($request);

        if ($storefrontUserId !== null) {
            return Cart::query()->firstOrCreate(
                ['user_id' => $storefrontUserId],
                ['session_token' => null],
            );
        }

        $token = $request->session()->get(self::SESSION_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            $token = $this->newToken();
            $request->session()->put(self::SESSION_TOKEN_KEY, $token);
        }

        return Cart::query()->firstOrCreate(
            ['session_token' => $token],
            ['user_id' => null],
        );
    }

    public function current(Request $request): ?Cart
    {
        $storefrontUserId = $this->customerContext->userId($request);

        if ($storefrontUserId !== null) {
            return Cart::query()
                ->where('user_id', $storefrontUserId)
                ->first();
        }

        $token = $request->session()->get(self::SESSION_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Cart::query()
            ->where('session_token', $token)
            ->whereNull('user_id')
            ->first();
    }

    public function itemCount(Request $request): string
    {
        $cart = $this->current($request);

        if ($cart === null) {
            return '0';
        }

        $count = (string) $cart->items()->sum('quantity');

        return rtrim(rtrim($count, '0'), '.') ?: '0';
    }

    private function newToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Cart::query()->where('session_token', $token)->exists());

        return $token;
    }
}
