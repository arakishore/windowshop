<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly AdminSettingsService $adminSettings,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $statusOptions = $this->orderStatuses();
        $paymentStatuses = $this->paymentStatuses();
        $filters = [
            'source' => (string) $request->query('source', ''),
            'status' => (string) $request->query('status', ''),
            'fulfillment' => (string) $request->query('fulfillment', ''),
            'search' => trim((string) $request->query('search', '')),
        ];
        $operationalSources = Order::merchantOperationalSources();

        $query = Order::query()
            ->with(['customer'])
            ->where('shop_id', $shop->getKey())
            ->where('merchant_id', $shop->merchant_id)
            ->whereIn('created_source', $operationalSources)
            ->when(in_array($filters['source'], $operationalSources, true), fn ($query) => $query->where('created_source', $filters['source']))
            ->when($filters['status'] !== '' && isset($statusOptions[$filters['status']]), fn ($query) => $query->where('order_status', $filters['status']))
            ->when(in_array($filters['fulfillment'], [Order::FULFILMENT_DELIVERY, Order::FULFILMENT_PICKUP], true), fn ($query) => $query->where('fulfilment_type', $filters['fulfillment']))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_mobile', 'like', "%{$search}%");
                });
            });

        $orders = $query
            ->latest()
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('merchant.orders.index', [
            'activeShop' => $shop,
            'orders' => $orders,
            'filters' => $filters,
            'orderStatuses' => $statusOptions,
            'orderStatusOptions' => $this->orderStatusOptions($statusOptions),
            'paymentStatuses' => $paymentStatuses,
            'paymentMethods' => $this->paymentMethods(),
            'fulfillmentTypes' => $this->fulfillmentTypes(),
            'sourceLabels' => $this->sourceLabels(),
            'operationalSources' => $operationalSources,
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $shop = $this->authorizeOrder($request, $order)->loadMissing(['city', 'state', 'country']);

        return view('merchant.orders.show', [
            'activeShop' => $shop,
            'order' => $order->load([
                'items.taxComponents',
                'totals',
                'customer',
                'statusHistories.changedBy',
            ]),
            'orderStatuses' => $this->orderStatuses(),
            'paymentStatuses' => $this->paymentStatuses(),
            'paymentMethods' => $this->paymentMethods(),
            'fulfillmentTypes' => $this->fulfillmentTypes(),
            'sourceLabels' => $this->sourceLabels(),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    private function authorizeOrder(Request $request, Order $order): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $order->shop_id === (int) $shop->getKey(), 404);
        abort_unless((int) $order->merchant_id === (int) $shop->merchant_id, 404);
        abort_unless(in_array($order->created_source, Order::merchantOperationalSources(), true), 404);

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
     * @return array<string, OrderStatus>
     */
    private function orderStatuses(): array
    {
        return OrderStatus::query()
            ->active()
            ->merchantVisible()
            ->ordered()
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * @param array<string, OrderStatus> $statuses
     * @return array<string, string>
     */
    private function orderStatusOptions(array $statuses): array
    {
        $fallback = [
            Order::STATUS_PENDING => 'Pending',
            Order::STATUS_CONFIRMED => 'Confirmed',
            Order::STATUS_PROCESSING => 'Processing',
            OrderStatus::CODE_PACKED => 'Packed',
            Order::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
            OrderStatus::CODE_SHIPPED => 'Shipped',
            OrderStatus::CODE_OUT_FOR_DELIVERY => 'Out for Delivery',
            OrderStatus::CODE_DELIVERED => 'Delivered',
            Order::STATUS_COMPLETED => 'Completed',
            Order::STATUS_CANCELLED => 'Cancelled',
        ];

        foreach ($fallback as $code => $label) {
            if (isset($statuses[$code])) {
                $fallback[$code] = $statuses[$code]->name;
            }
        }

        return $fallback;
    }

    /**
     * @return array<string, PaymentStatus>
     */
    private function paymentStatuses(): array
    {
        return PaymentStatus::query()
            ->active()
            ->merchantVisible()
            ->ordered()
            ->get()
            ->keyBy('code')
            ->all();
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
            Order::PAYMENT_METHOD_CREDIT => 'Credit',
            'cash_on_delivery' => 'Cash on Delivery',
            'cash_at_shop' => 'Cash at Shop',
            'merchant_upi' => 'Direct Merchant UPI',
            'online_payment' => 'Online Payment',
            Order::PAYMENT_METHOD_WALLET => 'Wallet',
            Order::PAYMENT_METHOD_OTHER => 'Other',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fulfillmentTypes(): array
    {
        return [
            Order::FULFILMENT_DELIVERY => 'Delivery',
            Order::FULFILMENT_PICKUP => 'Pickup',
            Order::FULFILMENT_COUNTER => 'Counter',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sourceLabels(): array
    {
        return [
            Order::SOURCE_STOREFRONT => 'Storefront',
            Order::SOURCE_POS => 'POS',
            Order::SOURCE_CUSTOMER_APP => 'Customer App',
            Order::SOURCE_ADMIN => 'Admin',
            Order::SOURCE_API => 'API',
        ];
    }
}
