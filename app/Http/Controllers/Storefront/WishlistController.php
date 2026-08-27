<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        private readonly StorefrontCustomerContext $customerContext,
    ) {
    }

    public function store(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $customer = $this->customer($request);

        if (! $customer instanceof Customer) {
            return $this->guestResponse($request);
        }

        $this->ensureStorefrontProduct($product);

        WishlistItem::query()->firstOrCreate([
            'customer_id' => $customer->getKey(),
            'product_id' => $product->getKey(),
        ]);

        return $this->wishlistResponse($request, true, $product);
    }

    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $customer = $this->customer($request);

        if (! $customer instanceof Customer) {
            return $this->guestResponse($request);
        }

        $this->ensureStorefrontProduct($product);

        WishlistItem::query()
            ->where('customer_id', $customer->getKey())
            ->where('product_id', $product->getKey())
            ->delete();

        return $this->wishlistResponse($request, false, $product);
    }

    private function customer(Request $request): ?Customer
    {
        $user = $this->customerContext->user($request);

        if (! $user instanceof User) {
            return null;
        }

        return $this->customerContext->customer($request);
    }

    private function ensureStorefrontProduct(Product $product): void
    {
        $defaultVariantId = ProductVariant::query()
            ->select('id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('status', 'active')
            ->where('is_sellable', true)
            ->where('is_default', true)
            ->where('mrp', '>', 0)
            ->where('selling_price', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(1);

        $exists = Product::query()
            ->whereKey($product->getKey())
            ->where('products.status', 'active')
            ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('shop', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereColumn('shops.merchant_id', 'products.merchant_id')
                ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active')))
            ->whereExists($defaultVariantId)
            ->exists();

        abort_unless($exists, 404);
    }

    private function guestResponse(Request $request): JsonResponse|RedirectResponse
    {
        $loginUrl = route('storefront.login');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Please login to use wishlist.',
                'login_url' => $loginUrl,
            ], 401);
        }

        return redirect()->route('storefront.login');
    }

    private function wishlistResponse(Request $request, bool $wishlisted, Product $product): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'wishlisted' => $wishlisted,
                'product_id' => (int) $product->getKey(),
            ]);
        }

        return back();
    }
}
