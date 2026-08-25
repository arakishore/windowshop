<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantCancellationReason;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Order\DeliveryCompletionService;
use App\Services\Order\OrderInventoryService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\PickupCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly AdminSettingsService $adminSettings,
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderInventoryService $orderInventoryService,
        private readonly PickupCompletionService $pickupCompletionService,
        private readonly DeliveryCompletionService $deliveryCompletionService,
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
        $order = $order->load([
            'items.product',
            'items.taxComponents',
            'totals',
            'customer',
            'statusHistories.changedBy',
            'comments.createdBy',
        ]);
        $allowedNextStatuses = $this->orderStatusService->allowedNextStatuses($order);

        return view('merchant.orders.show', [
            'activeShop' => $shop,
            'order' => $order,
            'allowedNextStatuses' => $allowedNextStatuses,
            'statusActionLabels' => (array) config('order_workflow.status_action_labels', []),
            'cancellationReasons' => $this->cancellationReasons((int) $shop->merchant_id, $allowedNextStatuses),
            'orderStatuses' => $this->orderStatuses(),
            'paymentStatuses' => $this->paymentStatuses(),
            'paymentMethods' => $this->paymentMethods(),
            'fulfillmentTypes' => $this->fulfillmentTypes(),
            'sourceLabels' => $this->sourceLabels(),
            'posCurrency' => $this->adminSettings->currencyConfig(),
        ]);
    }

    public function accept(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->orderStatusService->transition(
                $order,
                Order::STATUS_CONFIRMED,
                $request->user(),
                'Order accepted by merchant.',
                ['action' => 'merchant_accept'],
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order accepted successfully.');
    }

    public function startProcessing(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->orderStatusService->transition(
                $order,
                Order::STATUS_PROCESSING,
                $request->user(),
                'Order processing started.',
                ['action' => 'merchant_start_processing'],
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order processing started successfully.');
    }

    public function markReadyForPickup(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->orderStatusService->transition(
                $order,
                Order::STATUS_READY_FOR_PICKUP,
                $request->user(),
                'Order is ready for customer pickup.',
                ['action' => 'merchant_mark_ready_for_pickup'],
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order marked ready for pickup successfully.');
    }

    public function completePickup(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->pickupCompletionService->complete(
                $order,
                $request->user(),
                $request->boolean('payment_received'),
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Pickup completed successfully.');
    }

    public function markPacked(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->orderStatusService->transition(
                $order,
                OrderStatus::CODE_PACKED,
                $request->user(),
                'Order packed and ready for delivery.',
                ['action' => 'merchant_mark_packed'],
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order marked packed successfully.');
    }

    public function markShipped(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->orderStatusService->transition(
                $order,
                OrderStatus::CODE_SHIPPED,
                $request->user(),
                'Order handed over for delivery.',
                ['action' => 'merchant_mark_shipped'],
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order marked shipped successfully.');
    }

    public function markOutForDelivery(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->orderStatusService->transition(
                $order,
                OrderStatus::CODE_OUT_FOR_DELIVERY,
                $request->user(),
                'Order is out for delivery.',
                ['action' => 'merchant_mark_out_for_delivery'],
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order marked out for delivery successfully.');
    }

    public function markDelivered(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $this->deliveryCompletionService->markDelivered(
                $order,
                $request->user(),
                $request->boolean('payment_received'),
            );
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order delivered and completed successfully.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $shop = $this->authorizeOrder($request, $order);

        try {
            $data = $request->validate([
                'cancellation_reason_id' => ['required', 'integer'],
                'cancellation_note' => ['nullable', 'string', 'max:1000'],
            ]);
            $reason = $this->validatedCancellationReason((int) $shop->merchant_id, (int) $data['cancellation_reason_id']);
            $note = trim((string) ($data['cancellation_note'] ?? ''));

            if ($reason->requires_comment && $note === '') {
                throw ValidationException::withMessages([
                    'cancellation_note' => 'A cancellation note is required for this reason.',
                ]);
            }

            $historyNote = 'Cancelled by merchant. Reason: '.$reason->name.'.';
            if ($note !== '') {
                $historyNote .= ' Note: '.$note;
            }

            DB::transaction(function () use ($order, $request, $historyNote, $reason, $note): void {
                $restored = $this->orderInventoryService->restoreForCancellation($order);

                $this->orderStatusService->transition(
                    $order,
                    Order::STATUS_CANCELLED,
                    $request->user(),
                    $historyNote,
                    [
                        'action' => 'merchant_cancel',
                        'reason_id' => $reason->getKey(),
                        'reason_code' => $reason->code,
                        'reason_name' => $reason->name,
                        'note' => $note !== '' ? $note : null,
                        'stock_restored' => $restored,
                    ],
                );
            });
        } catch (ValidationException $exception) {
            return $this->transitionFailed($exception);
        }

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Order cancelled successfully.');
    }

    public function storeComment(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(OrderComment::visibilityOptions())],
            'notify_customer' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
        ]);

        $comment = trim((string) $data['comment']);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'Please enter a comment.',
            ]);
        }

        $visibility = (string) $data['visibility'];
        $notifyCustomer = $visibility === OrderComment::VISIBILITY_CUSTOMER && (bool) ($data['notify_customer'] ?? false);
        $notifyEmail = $notifyCustomer && (bool) ($data['notify_email'] ?? false);
        $notifySms = $notifyCustomer && (bool) ($data['notify_sms'] ?? false);
        $notifyWhatsapp = $notifyCustomer && (bool) ($data['notify_whatsapp'] ?? false);

        if ($notifyCustomer && ! ($notifyEmail || $notifySms || $notifyWhatsapp)) {
            throw ValidationException::withMessages([
                'notify_channels' => 'Select at least one notification channel.',
            ]);
        }

        $order->comments()->create([
            'author_type' => OrderComment::AUTHOR_MERCHANT,
            'comment' => $comment,
            'visibility' => $visibility,
            'notify_customer' => $notifyCustomer,
            'notify_email' => $notifyEmail,
            'notify_sms' => $notifySms,
            'notify_whatsapp' => $notifyWhatsapp,
            'created_by' => $request->user()?->getKey(),
        ]);

        return redirect()
            ->route('merchant.orders.show', $order)
            ->with('success', 'Comment added successfully.');
    }

    private function authorizeOrder(Request $request, Order $order): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $order->shop_id === (int) $shop->getKey(), 404);
        abort_unless((int) $order->merchant_id === (int) $shop->merchant_id, 404);
        abort_unless(in_array($order->created_source, Order::merchantOperationalSources(), true), 404);

        return $shop;
    }

    /**
     * @param array<int, string> $allowedNextStatuses
     * @return \Illuminate\Support\Collection<int, MerchantCancellationReason>
     */
    private function cancellationReasons(int $merchantId, array $allowedNextStatuses)
    {
        if (! in_array(Order::STATUS_CANCELLED, $allowedNextStatuses, true)) {
            return collect();
        }

        return MerchantCancellationReason::query()
            ->forMerchant($merchantId)
            ->active()
            ->where('merchant_selectable', true)
            ->ordered()
            ->get();
    }

    private function validatedCancellationReason(int $merchantId, int $reasonId): MerchantCancellationReason
    {
        $reason = MerchantCancellationReason::query()
            ->forMerchant($merchantId)
            ->active()
            ->where('merchant_selectable', true)
            ->whereKey($reasonId)
            ->first();

        if (! $reason instanceof MerchantCancellationReason) {
            throw ValidationException::withMessages([
                'cancellation_reason_id' => 'Please select a valid cancellation reason.',
            ]);
        }

        return $reason;
    }

    private function transitionFailed(ValidationException $exception): RedirectResponse
    {
        $message = Arr::first(Arr::flatten($exception->errors())) ?: 'Order status could not be updated.';

        return back()
            ->withInput()
            ->withErrors($exception->errors())
            ->with('error', $message);
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
