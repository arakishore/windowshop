<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderExchange;
use App\Models\ReturnReason;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\DateTime\BusinessTimeService;
use App\Services\Merchant\MerchantSettingsService;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Order\OrderExchangeService;
use App\Services\Order\OrderRefundService;
use App\Services\Order\OrderReturnExchangeEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesHistoryController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly AdminSettingsService $adminSettings,
        private readonly MerchantSettingsService $settings,
        private readonly OrderRefundService $refundService,
        private readonly OrderExchangeService $exchangeService,
        private readonly OrderReturnExchangeEligibilityService $returnExchangeEligibility,
        private readonly BusinessTimeService $businessTime,
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
            ->where('orders.shop_id', $shop->getKey())
            // Exchange replacement orders carry operational paid status so receipts/stock work,
            // but actual newly collected/refunded money lives on order_exchanges settlement fields.
            // Sales and collection reports must therefore stay limited to original POS sales.
            ->where('orders.created_source', Order::SOURCE_POS)
            ->where('orders.order_status', Order::STATUS_COMPLETED)
            ->when($filters['payment_method'] !== '', fn ($query) => $query->where('orders.payment_method', $filters['payment_method']))
            ->when($filters['customer'] !== '', fn ($query) => $query->where('orders.customer_id', $filters['customer']))
            ->when($filters['from'] !== '' || $filters['to'] !== '', function ($query) use ($filters): void {
                [$start, $end] = $this->businessTime->dateRangeToUtc(
                    $filters['from'] !== '' ? $filters['from'] : null,
                    $filters['to'] !== '' ? $filters['to'] : null,
                );
                $query->where('orders.created_at', '>=', $start)
                    ->where('orders.created_at', '<', $end);
            })
            ->when($filters['status'] === 'refunded', fn ($query) => $query->where('orders.payment_status', Order::PAYMENT_REFUNDED))
            ->when($filters['status'] === 'partially_refunded', fn ($query) => $query->where('orders.payment_status', Order::PAYMENT_PARTIALLY_REFUNDED))
            ->when($filters['status'] === 'completed', fn ($query) => $query->whereNotIn('orders.payment_status', [Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED]))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('orders.order_number', 'like', "%{$search}%")
                        ->orWhere('orders.customer_name', 'like', "%{$search}%")
                        ->orWhere('orders.customer_mobile', 'like', "%{$search}%")
                        ->orWhereHas('createdBy', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            });

        $summary = (clone $query)->selectRaw('
            COUNT(*) as transactions,
            COALESCE(SUM(subtotal), 0) as subtotal,
            COALESCE(SUM(discount_total), 0) as discount_total,
            COALESCE(SUM(tax_total), 0) as tax_total,
            COALESCE(SUM(grand_total), 0) as total_sales
        ')->first();
        $itemsSold = (clone $query)
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->sum('order_items.quantity');
        $taxSummary = $this->taxSummary(clone $query);
        $componentSummary = $this->componentSummary(clone $query);

        $orders = $query
            ->latest()
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('merchant.sales.index', [
            'activeShop' => $shop,
            'orders' => $orders,
            'filters' => $filters,
            'summary' => [
                'subtotal' => (float) ($summary->subtotal ?? 0),
                'discount_total' => (float) ($summary->discount_total ?? 0),
                'tax_total' => (float) ($summary->tax_total ?? 0),
                'total_sales' => (float) ($summary->total_sales ?? 0),
                'transactions' => (int) ($summary->transactions ?? 0),
                'items_sold' => (int) $itemsSold,
                'average_sale' => (int) ($summary->transactions ?? 0) > 0
                    ? (float) $summary->total_sales / (int) $summary->transactions
                    : 0,
            ],
            'taxSummary' => $taxSummary,
            'componentSummary' => $componentSummary,
            'customers' => Order::query()
                ->where('shop_id', $shop->getKey())
                ->where('created_source', Order::SOURCE_POS)
                ->whereNotNull('customer_id')
                ->select('customer_id', 'customer_name')
                ->distinct()
                ->orderBy('customer_name')
                ->get(),
            'paymentMethods' => $this->paymentMethods(),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    private function taxSummary($query)
    {
        return $query
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where(function ($query): void {
                $query->where('order_items.line_tax', '>', 0)
                    ->orWhereNotNull('order_items.tax_class_name')
                    ->orWhereNotNull('order_items.tax_rate');
            })
            ->selectRaw("
                COALESCE(order_items.tax_class_name, 'No tax class') as tax_class_name,
                order_items.tax_rate,
                order_items.price_mode,
                COALESCE(SUM(order_items.taxable_amount), 0) as taxable_amount,
                COALESCE(SUM(order_items.line_tax), 0) as tax_amount,
                COALESCE(SUM(order_items.line_total), 0) as line_total
            ")
            ->groupBy('order_items.tax_class_name', 'order_items.tax_rate', 'order_items.price_mode')
            ->orderBy('order_items.tax_class_name')
            ->orderBy('order_items.tax_rate')
            ->get();
    }

    private function componentSummary($query)
    {
        return $query
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('order_item_tax_components', 'order_items.id', '=', 'order_item_tax_components.order_item_id')
            ->selectRaw('
                order_item_tax_components.component_code,
                order_item_tax_components.component_name,
                order_item_tax_components.jurisdiction_type,
                MIN(order_item_tax_components.rate) as min_rate,
                MAX(order_item_tax_components.rate) as max_rate,
                COALESCE(SUM(order_item_tax_components.amount), 0) as amount
            ')
            ->groupBy(
                'order_item_tax_components.component_code',
                'order_item_tax_components.component_name',
                'order_item_tax_components.jurisdiction_type',
                'order_item_tax_components.sort_order',
            )
            ->orderBy('order_item_tax_components.sort_order')
            ->orderBy('order_item_tax_components.component_code')
            ->get();
    }

    public function show(Request $request, Order $order): View
    {
        $shop = $this->authorizeOrder($request, $order);

        return view('merchant.sales.show', [
            'activeShop' => $shop,
            'order' => $order->load(['items.taxComponents', 'createdBy', 'refunds.items', 'exchanges.replacementOrder', 'customer']),
            'refundableQuantities' => $this->refundService->refundableQuantities($order->loadMissing('items')),
            'exchangeableQuantities' => $this->exchangeService->exchangeableQuantities($order->loadMissing('items')),
            'returnExchangeEligibility' => $this->returnExchangeEligibility->forOrder($order),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    public function exchange(Request $request, Order $order): View
    {
        $shop = $this->authorizeOrder($request, $order);
        $order->load(['items', 'exchanges.items']);

        return view('merchant.sales.exchange', [
            'activeShop' => $shop,
            'order' => $order,
            'exchangeableQuantities' => $this->exchangeService->exchangeableQuantities($order),
            'returnExchangeEligibility' => $this->returnExchangeEligibility->forOrder($order),
            'replacementVariants' => $this->replacementSelector((int) $shop->merchant_id) !== 'search'
                ? $this->replacementVariants($shop)
                : collect(),
            'replacementSelector' => $this->replacementSelector((int) $shop->merchant_id),
            'paymentMethods' => $this->paymentMethods(),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    public function processExchange(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'settlement_method' => ['required', Rule::in(array_keys($this->paymentMethods()))],
            'notes' => ['nullable', 'string', 'max:500'],
            'returned_items' => ['required', 'array'],
            'returned_items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'returned_items.*.restock' => ['nullable', 'boolean'],
            'returned_items.*.do_not_restock' => ['nullable', 'boolean'],
            'replacement_items' => ['required', 'array'],
            'replacement_items.*.product_variant_id' => ['nullable', 'integer'],
            'replacement_items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'policy_override_reason' => ['nullable', 'string', 'max:120'],
            'policy_override_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $this->requirePolicyOverrideIfNeeded($order, 'exchange', $data, 'returned_items');
        $data['notes'] = $this->notesWithPolicyOverride($data);

        $exchange = $this->exchangeService->create($order, $data, $request->user());

        return redirect()
            ->route('merchant.sales.exchange.receipt', $exchange)
            ->with('success', 'Exchange processed successfully.');
    }

    public function exchangeReceipt(Request $request, OrderExchange $exchange): View
    {
        $shop = $this->activeShop($request)->load(['city', 'merchant']);
        abort_unless((int) $exchange->shop_id === (int) $shop->getKey(), 404);
        abort_unless((int) $exchange->merchant_id === (int) $shop->merchant_id, 404);

        return view('merchant.sales.exchange-receipt', [
            'activeShop' => $shop,
            'exchange' => $exchange->load(['items.orderItem', 'replacementOrder.items', 'originalOrder', 'createdBy']),
            'autoPrint' => $request->boolean('print'),
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
                ->where('code', '!=', 'exchange')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'refundableQuantities' => $this->refundService->refundableQuantities($order),
            'returnExchangeEligibility' => $this->returnExchangeEligibility->forOrder($order),
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
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.do_not_restock' => ['nullable', 'boolean'],
            'policy_override_reason' => ['nullable', 'string', 'max:120'],
            'policy_override_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $this->requirePolicyOverrideIfNeeded($order, 'refund', $data, 'items');
        $data['notes'] = $this->notesWithPolicyOverride($data);

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

    /**
     * @param array<string, mixed> $data
     */
    private function requirePolicyOverrideIfNeeded(Order $order, string $type, array $data, string $itemsKey): void
    {
        if ($order->created_source === Order::SOURCE_POS) {
            return;
        }

        if (! $this->returnExchangeEligibility->merchantOverrideRequiredForSelected(
            $order->loadMissing(['items', 'statusHistories']),
            $type,
            $data[$itemsKey] ?? [],
        )) {
            return;
        }

        $reason = trim((string) ($data['policy_override_reason'] ?? ''));
        $comment = trim((string) ($data['policy_override_comment'] ?? ''));

        if ($reason !== '' && $comment !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'policy_override_comment' => 'Enter a policy override reason and comment to proceed outside the customer policy.',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notesWithPolicyOverride(array $data): ?string
    {
        $notes = trim((string) ($data['notes'] ?? ''));
        $reason = trim((string) ($data['policy_override_reason'] ?? ''));
        $comment = trim((string) ($data['policy_override_comment'] ?? ''));

        if ($reason === '' && $comment === '') {
            return $notes === '' ? null : $notes;
        }

        $override = 'Policy override: '.$reason.'. '.$comment;
        $combined = trim($notes === '' ? $override : $notes."\n\n".$override);

        return $combined === '' ? null : $combined;
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

    private function replacementSelector(int $merchantId): string
    {
        $selector = (string) $this->settings->get($merchantId, 'pos', 'exchange.replacement_selector', 'both');

        return in_array($selector, ['search', 'dropdown', 'both'], true) ? $selector : 'both';
    }

    private function replacementVariants(Shop $shop)
    {
        return \App\Models\ProductVariant::query()
            ->with('product')
            ->where('shop_id', $shop->getKey())
            ->where('status', 'active')
            ->whereHas('product', fn ($query) => $query
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->where('status', 'active'))
            ->orderBy('id')
            ->limit(500)
            ->get();
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
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
        ];
    }

}
