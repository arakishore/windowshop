<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Merchant\PosProductSearchService;
use App\Services\Order\OrderCreationService;
use App\Services\Product\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosController extends Controller
{
    private const POS_PRODUCT_LOAD_LIMIT = 1000;

    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly ProductImageService $productImageService,
        private readonly OrderCreationService $orderCreationService,
        private readonly PosProductSearchService $posProductSearchService,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category_id' => (int) $request->query('category_id', 0),
        ];
        $categoryIds = $this->categoryIdsForShop($shop);

        $products = Product::query()
            ->with([
                'category.parent',
                'primaryImage',
                'variants' => fn ($query) => $query
                    ->with(['attributes.group', 'attributes.value'])
                    ->where('status', 'active')
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->where('shop_id', $shop->getKey())
            ->where('status', 'active')
            ->whereIn('product_category_id', $categoryIds)
            ->when($filters['category_id'] > 0, fn ($query) => $query->where('product_category_id', $filters['category_id']))
            ->orderBy('product_name')
            ->limit(self::POS_PRODUCT_LOAD_LIMIT)
            ->get();

        return view('merchant.pos.index', [
            'activeShop' => $shop,
            'categories' => $this->categoriesForShop($shop, $categoryIds),
            'filters' => $filters,
            'posItems' => $this->posItems($products, ''),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'elapsed_seconds' => ['nullable', 'integer', 'min:0'],
            'fulfilment_type' => ['required', Rule::in(['counter', 'pickup'])],
            'payment_method' => ['required', Rule::in([
                Order::PAYMENT_METHOD_CASH,
                Order::PAYMENT_METHOD_UPI,
                Order::PAYMENT_METHOD_CARD,
                Order::PAYMENT_METHOD_WALLET,
                Order::PAYMENT_METHOD_OTHER,
            ])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'upi_txn' => ['nullable', 'string', 'max:255'],
            'terminal_id' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = $this->orderCreationService->create([
            'shop_id' => $shop->getKey(),
            'created_source' => 'pos',
            'fulfilment_type' => $data['fulfilment_type'],
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'upi_txn' => $data['upi_txn'] ?? null,
            'terminal_id' => $data['terminal_id'] ?? null,
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'amount_paid' => $data['amount_paid'],
            'elapsed_seconds' => $data['elapsed_seconds'] ?? 0,
            'items' => $data['items'],
        ], $request->user());

        return response()->json([
            'message' => 'Sale completed successfully.',
            'order' => [
                'id' => $order->getKey(),
                'number' => $order->order_number,
                'grand_total' => $order->grand_total,
                'amount_paid' => $order->amount_paid,
                'change_amount' => $order->change_amount,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
                'upi_txn' => $order->upi_txn,
                'terminal_id' => $order->terminal_id,
                'elapsed_seconds' => $order->elapsed_seconds,
                'items_count' => $order->items->sum('quantity'),
                'receipt_url' => route('merchant.pos.receipt', $order->getKey()),
                'print_url' => route('merchant.pos.receipt', ['order' => $order->getKey(), 'print' => 1]),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'scanner_mode' => ['nullable', 'boolean'],
        ]);

        $result = $this->posProductSearchService->search(
            $shop,
            (string) $data['q'],
            (bool) ($data['scanner_mode'] ?? false),
        );

        return response()->json($result, match ($result['status'] ?? null) {
            'conflict' => 409,
            default => 200,
        });
    }

    public function receipt(Request $request, int $order): View
    {
        $shop = $this->activeShop($request)->load(['city', 'merchant']);
        $orderModel = Order::query()
            ->with(['createdBy', 'items'])
            ->where('shop_id', $shop->getKey())
            ->whereKey($order)
            ->firstOrFail();

        return view('merchant.pos.receipt', [
            'activeShop' => $shop,
            'autoPrint' => $request->boolean('print'),
            'order' => $orderModel,
        ]);
    }

    public function recentSales(Request $request): JsonResponse
    {
        $shop = $this->activeShop($request);

        $orders = Order::query()
            ->where('shop_id', $shop->getKey())
            ->where('created_source', Order::SOURCE_POS)
            ->where('order_status', Order::STATUS_COMPLETED)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->getKey(),
                'number' => $order->order_number,
                'created_at' => $order->created_at?->format('M d · h:i A'),
                'grand_total' => $order->grand_total,
                'receipt_url' => route('merchant.pos.receipt', $order->getKey()),
                'print_url' => route('merchant.pos.receipt', ['order' => $order->getKey(), 'print' => 1]),
            ])
            ->values();

        return response()->json([
            'sales' => $orders,
        ]);
    }

    private function activeShop(Request $request): Shop
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $shop = $this->shopContextService->resolveActiveShop(
            $this->shopContextService->activeShops($merchant),
            $request->session()->get('active_shop_id'),
        );

        abort_unless($shop instanceof Shop, 403);

        return $shop;
    }

    /**
     * @return array<int, int>
     */
    private function categoryIdsForShop(Shop $shop): array
    {
        return ProductCategory::query()
            ->where('id', $shop->root_product_category_id)
            ->orWhereHas('parent', fn ($query) => $query->where('id', $shop->root_product_category_id)
                ->orWhereHas('parent', fn ($query) => $query->where('id', $shop->root_product_category_id)))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function categoriesForShop(Shop $shop, array $categoryIds): Collection
    {
        return ProductCategory::query()
            ->whereIn('id', $categoryIds)
            ->where('id', '!=', $shop->root_product_category_id)
            ->where('status', 'active')
            ->withCount(['products' => fn ($query) => $query
                ->where('shop_id', $shop->getKey())
                ->where('status', 'active')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (ProductCategory $category): bool => (int) $category->products_count > 0)
            ->values();
    }

    private function posItems(Collection $products, string $search): Collection
    {
        return $products
            ->flatMap(function (Product $product) use ($search): Collection {
                $variants = $this->variantsForSearch($product, $search);

                return $variants->map(function (ProductVariant $variant) use ($product): array {
                    $attributeSearch = $variant->attributes
                        ->flatMap(fn ($attribute): array => [
                            $attribute->group?->name,
                            $attribute->value?->name,
                            $attribute->value?->code,
                        ])
                        ->filter()
                        ->implode(' ');

                    return [
                        'id' => $variant->getKey(),
                        'product_id' => $product->getKey(),
                        'product_name' => $product->product_name,
                        'variant_name' => $variant->name,
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'price' => (float) $variant->selling_price,
                        'stock' => (int) $variant->stock_quantity,
                        'image_url' => $this->imageUrl($product, $variant),
                        'category_id' => $product->product_category_id,
                        'category_name' => $product->category?->name,
                        'attribute_search' => $attributeSearch,
                    ];
                });
            })
            ->values();
    }

    private function variantsForSearch(Product $product, string $search): Collection
    {
        if ($search === '' || str_contains(strtolower($product->product_name), strtolower($search))) {
            return $product->variants;
        }

        $needle = strtolower($search);

        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => str_contains(strtolower((string) $variant->sku), $needle)
                || str_contains(strtolower((string) $variant->barcode), $needle))
            ->values();
    }

    private function imageUrl(Product $product, ProductVariant $variant): ?string
    {
        $path = $this->productImageService
            ->galleryForVariant($product, $variant)
            ->first()
            ?->image_path;

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
