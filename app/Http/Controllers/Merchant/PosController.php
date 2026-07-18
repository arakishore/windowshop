<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantCustomer;
use App\Models\MerchantCustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Merchant\MerchantCustomerAddressService;
use App\Services\Merchant\MerchantSettingsService;
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
        private readonly MerchantCustomerAddressService $customerAddressService,
        private readonly MerchantSettingsService $settings,
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
            'fulfilment_type' => ['required', Rule::in(['counter', 'pickup', 'delivery'])],
            'customer_id' => ['nullable', 'integer'],
            'shipping_address_id' => ['nullable', 'integer'],
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
            'order_discount' => ['nullable', 'array'],
            'order_discount.type' => ['nullable', Rule::in([Order::DISCOUNT_TYPE_PERCENT, Order::DISCOUNT_TYPE_AMOUNT])],
            'order_discount.value' => ['nullable', 'numeric', 'min:0'],
            'order_discount.reason' => ['nullable', 'string', 'max:80'],
            'order_discount.note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_type' => ['nullable', Rule::in([Order::DISCOUNT_TYPE_PERCENT, Order::DISCOUNT_TYPE_AMOUNT])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = $this->orderCreationService->create([
            'shop_id' => $shop->getKey(),
            'created_source' => 'pos',
            'fulfilment_type' => $data['fulfilment_type'],
            'customer_id' => $data['customer_id'] ?? null,
            'shipping_address_id' => $data['shipping_address_id'] ?? null,
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'upi_txn' => $data['upi_txn'] ?? null,
            'terminal_id' => $data['terminal_id'] ?? null,
            'order_discount' => $data['order_discount'] ?? [],
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
                'fulfilment_type' => $order->fulfilment_type,
                'customer_name' => $order->customer_name,
                'shipping_address_line_1' => $order->shipping_address_line_1,
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

    public function customers(Request $request): JsonResponse
    {
        $shop = $this->activeShop($request);
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['customers' => []]);
        }

        $customers = MerchantCustomer::query()
            ->where('merchant_id', $shop->merchant_id)
            ->where('status', MerchantCustomer::STATUS_ACTIVE)
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('mobile', 'like', "%{$query}%")
                    ->orWhere('mobile_normalized', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('customer_code', 'like', "%{$query}%");
            })
            ->withCount(['addresses' => fn ($builder) => $builder->where('status', MerchantCustomerAddress::STATUS_ACTIVE)])
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (MerchantCustomer $customer): array => [
                'id' => $customer->getKey(),
                'route_key' => $customer->getRouteKey(),
                'name' => $customer->name,
                'customer_code' => $customer->customer_code,
                'mobile' => $customer->mobile,
                'mobile_country_code' => $customer->mobile_country_code,
                'email' => $customer->email,
                'addresses_count' => $customer->addresses_count,
            ])
            ->values();

        return response()->json(['customers' => $customers]);
    }

    public function customerAddresses(Request $request, MerchantCustomer $customer): JsonResponse
    {
        $this->authorizePosCustomer($request, $customer);

        return response()->json([
            'addresses' => $customer->addresses()
                ->with(['city', 'state', 'country'])
                ->where('status', MerchantCustomerAddress::STATUS_ACTIVE)
                ->orderByDesc('is_default_shipping')
                ->orderBy('label')
                ->get()
                ->map(fn (MerchantCustomerAddress $address): array => $this->addressPayload($address))
                ->values(),
        ]);
    }

    public function storeCustomerAddress(Request $request, MerchantCustomer $customer): JsonResponse
    {
        $this->authorizePosCustomer($request, $customer);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_mobile_country_code' => ['nullable', 'string', 'max:10'],
            'recipient_mobile' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:190'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default_shipping' => ['nullable', 'boolean'],
        ]);

        $address = $this->customerAddressService->create($customer, [
            ...$data,
            'is_default_billing' => false,
            'status' => MerchantCustomerAddress::STATUS_ACTIVE,
        ]);

        return response()->json([
            'message' => 'Address added successfully.',
            'address' => $this->addressPayload($address),
        ], 201);
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
            'receiptSettings' => [
                'showShopName' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_shop_name', true),
                'showAddress' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_address', true),
                'showPhone' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_phone', true),
                'showGstNumber' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_gst_number', true),
                'showCustomer' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_customer', true),
                'showCashier' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_cashier', true),
                'showOrderNumber' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_order_number', true),
                'showBarcode' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_barcode', false),
                'showQrCode' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_qr_code', true),
                'showTaxBreakdown' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.show_tax_breakdown', true),
                'showItemSku' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.line_item.show_sku', false),
                'showItemHsnCode' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.line_item.show_hsn_code', false),
                'showHsnSummary' => (bool) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.line_item.show_hsn_summary', false),
                'footerText' => (string) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.footer', 'Thank you for shopping with us.'),
                'returnPolicy' => (string) $this->settings->get((int) $shop->merchant_id, 'pos', 'receipt.return_policy', ''),
            ],
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

    private function authorizePosCustomer(Request $request, MerchantCustomer $customer): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $customer->merchant_id === (int) $shop->merchant_id, 404);
        abort_unless($customer->status === MerchantCustomer::STATUS_ACTIVE, 404);

        return $shop;
    }

    private function addressPayload(MerchantCustomerAddress $address): array
    {
        return [
            'id' => $address->getKey(),
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'recipient_mobile_country_code' => $address->recipient_mobile_country_code,
            'recipient_mobile' => $address->recipient_mobile,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'landmark' => $address->landmark,
            'city' => $address->city?->name,
            'state' => $address->state?->name,
            'country' => $address->country?->name,
            'postal_code' => $address->postal_code,
            'is_default_shipping' => $address->is_default_shipping,
        ];
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
