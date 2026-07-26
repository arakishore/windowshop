<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnReason;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Order\OrderRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesHistoryController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly AdminSettingsService $adminSettings,
        private readonly OrderRefundService $refundService,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'status' => (string) $request->query('status', ''),
            'payment_method' => (string) $request->query('payment_method', ''),
            'customer' => (string) $request->query('customer', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'search' => trim((string) $request->query('search', '')),
        ];

        $query = Order::query()
            ->with(['customer', 'createdBy'])
            ->where('shop_id', $shop->getKey())
            ->where('created_source', Order::SOURCE_POS)
            ->where('order_status', Order::STATUS_COMPLETED)
            ->when($filters['payment_method'] !== '', fn ($query) => $query->where('payment_method', $filters['payment_method']))
            ->when($filters['customer'] !== '', fn ($query) => $query->where('customer_id', $filters['customer']))
            ->when($filters['from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['status'] === 'refunded', fn ($query) => $query->where('payment_status', Order::PAYMENT_REFUNDED))
            ->when($filters['status'] === 'partially_refunded', fn ($query) => $query->where('payment_status', Order::PAYMENT_PARTIALLY_REFUNDED))
            ->when($filters['status'] === 'completed', fn ($query) => $query->whereNotIn('payment_status', [Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED]))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_mobile', 'like', "%{$search}%")
                        ->orWhereHas('createdBy', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            });

        $summary = (clone $query)->selectRaw('COUNT(*) as transactions, COALESCE(SUM(grand_total), 0) as total_sales')->first();
        $itemsSold = (clone $query)
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->sum('order_items.quantity');

        $orders = $query
            ->latest()
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('merchant.sales.index', [
            'activeShop' => $shop,
            'orders' => $orders,
            'filters' => $filters,
            'summary' => [
                'total_sales' => (float) ($summary->total_sales ?? 0),
                'transactions' => (int) ($summary->transactions ?? 0),
                'items_sold' => (int) $itemsSold,
                'average_sale' => (int) ($summary->transactions ?? 0) > 0
                    ? (float) $summary->total_sales / (int) $summary->transactions
                    : 0,
            ],
            'customers' => Order::query()
                ->where('shop_id', $shop->getKey())
                ->whereNotNull('customer_id')
                ->select('customer_id', 'customer_name')
                ->distinct()
                ->orderBy('customer_name')
                ->get(),
            'paymentMethods' => $this->paymentMethods(),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $shop = $this->authorizeOrder($request, $order);

        return view('merchant.sales.show', [
            'activeShop' => $shop,
            'order' => $order->load(['items', 'createdBy', 'refunds.items', 'customer']),
            'refundableQuantities' => $this->refundService->refundableQuantities($order->loadMissing('items')),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    public function refund(Request $request, Order $order): View
    {
        $shop = $this->authorizeOrder($request, $order);
        $order->load(['items', 'refunds.items']);

        return view('merchant.sales.refund', [
            'activeShop' => $shop,
            'order' => $order,
            'returnReasons' => ReturnReason::query()
                ->where('merchant_id', $shop->merchant_id)
                ->where('status', ReturnReason::STATUS_ACTIVE)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'refundableQuantities' => $this->refundService->refundableQuantities($order),
            'paymentMethods' => ['original' => 'Use original method'] + $this->paymentMethods(),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    public function processRefund(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'return_reason_id' => ['required', 'integer'],
            'refund_method' => ['required', Rule::in(array_keys(['original' => 'Original'] + $this->paymentMethods()))],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.do_not_restock' => ['nullable', 'boolean'],
        ]);

        $this->refundService->create($order, $data, $request->user());

        return redirect()
            ->route('merchant.sales.show', $order)
            ->with('success', 'Refund processed successfully.');
    }

    private function authorizeOrder(Request $request, Order $order): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $order->shop_id === (int) $shop->getKey(), 404);
        abort_unless((int) $order->merchant_id === (int) $shop->merchant_id, 404);
        abort_unless($order->order_status === Order::STATUS_COMPLETED, 404);

        return $shop;
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
     * @return array<string, string>
     */
    private function paymentMethods(): array
    {
        return [
            Order::PAYMENT_METHOD_CASH => 'Cash',
            Order::PAYMENT_METHOD_CARD => 'Card',
            Order::PAYMENT_METHOD_UPI => 'UPI',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
        ];
    }

}
