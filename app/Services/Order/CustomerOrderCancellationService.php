<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\CustomerCancellationReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOrderCancellationService
{
    public function __construct(
        private readonly OrderInventoryService $inventory,
        private readonly OrderStatusService $statuses,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function reasonOptions(): array
    {
        return CustomerCancellationReason::query()
            ->active()
            ->ordered()
            ->get(['code', 'name', 'requires_comment'])
            ->mapWithKeys(fn (CustomerCancellationReason $reason): array => [
                $reason->code => [
                    'name' => $reason->name,
                    'requires_comment' => $reason->requires_comment,
                ],
            ])
            ->all();
    }

    public function canCancel(Order $order): bool
    {
        return $this->hasAllowedPaymentMethod($order)
            && $this->doesNotRequireRefund($order)
            && $this->statuses->canCustomerCancel($order);
    }

    public function cancel(Order $order, User $actor, string $reason, ?string $note = null): Order
    {
        $reasonModel = $this->activeReason($reason);
        $reasonLabel = $reasonModel->name;
        $note = trim((string) $note);

        if (! $this->canCancel($order)) {
            throw ValidationException::withMessages([
                'order_status' => 'This order cannot be cancelled from the customer account.',
            ]);
        }

        if ($reasonModel->requires_comment && $note === '') {
            throw ValidationException::withMessages([
                'cancellation_note' => 'Please add a note when selecting Other.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $reason, $reasonLabel, $note): Order {
            $restored = $this->inventory->restoreForCancellation($order);
            $historyNote = 'Customer requested cancellation. Reason: '.$reasonLabel.'.';

            if ($note !== '') {
                $historyNote .= ' Note: '.$note;
            }

            return $this->statuses->transitionForCustomerCancellation(
                $order,
                $actor,
                $historyNote,
                [
                    'action' => 'customer_cancel',
                    'initiated_by' => 'customer',
                    'reason_code' => $reason,
                    'reason_name' => $reasonLabel,
                    'customer_safe_reason' => true,
                    'note' => $note !== '' ? $note : null,
                    'stock_restored' => $restored,
                ],
            );
        });
    }

    public function reasonLabel(string $reason): string
    {
        return $this->activeReason($reason)->name;
    }

    private function activeReason(string $reason): CustomerCancellationReason
    {
        $model = CustomerCancellationReason::query()
            ->active()
            ->where('code', $reason)
            ->first();

        if (! $model instanceof CustomerCancellationReason) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Please select a valid cancellation reason.',
            ]);
        }

        return $model;
    }

    private function hasAllowedPaymentMethod(Order $order): bool
    {
        $methods = (array) config("order_workflow.customer_cancellation.allowed_payment_methods.{$order->fulfilment_type}", []);

        return in_array($order->payment_method, $methods, true);
    }

    private function doesNotRequireRefund(Order $order): bool
    {
        $refundRequiredStatuses = (array) config('order_workflow.customer_cancellation.refund_required_payment_statuses', []);

        return (float) $order->amount_paid <= 0
            && ! in_array($order->payment_status, $refundRequiredStatuses, true);
    }
}
