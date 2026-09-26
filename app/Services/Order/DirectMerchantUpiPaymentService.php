<?php

namespace App\Services\Order;

use App\Models\DirectMerchantUpiAttempt;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Checkout\StorefrontPaymentMethodService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectMerchantUpiPaymentService
{
    public function __construct(private readonly DirectMerchantUpiAttemptService $attempts) {}

    public function confirm(Order $order, User $actor, string $confirmedReference): Order
    {
        $confirmedReference = $this->attempts->validatedReference($confirmedReference, 'upi_txn');

        return DB::transaction(function () use ($order, $actor, $confirmedReference): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertDirectUpi($locked);

            if (in_array($locked->order_status, [Order::STATUS_CANCELLED, Order::STATUS_COMPLETED], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Payment cannot be confirmed for a cancelled or completed order.',
                ]);
            }

            if ($locked->payment_status === Order::PAYMENT_PAID) {
                throw ValidationException::withMessages([
                    'upi_txn' => 'This payment has already been confirmed.',
                ]);
            }

            if (! in_array($locked->payment_status, [Order::PAYMENT_PENDING, Order::PAYMENT_UNPAID], true)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'This payment is not in a state that can be confirmed.',
                ]);
            }

            $duplicate = Order::query()
                ->where('payment_method', StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI)
                ->where('payment_status', Order::PAYMENT_PAID)
                ->whereRaw('UPPER(upi_txn) = ?', [strtoupper($confirmedReference)])
                ->whereKeyNot($locked->getKey())
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'upi_txn' => 'This UPI transaction reference is already confirmed on another Direct Merchant UPI order.',
                ]);
            }

            $attempt = $this->attempts->currentSubmittedForUpdate($locked);

            $confirmedAt = now();
            $attempt->forceFill([
                'status' => DirectMerchantUpiAttempt::STATUS_VERIFIED,
                'active_slot' => null,
                'confirmed_reference' => $confirmedReference,
                'verified_at' => $confirmedAt,
                'verified_by' => $actor->getKey(),
            ])->save();
            $locked->forceFill([
                'upi_txn' => $confirmedReference,
                'payment_status' => Order::PAYMENT_PAID,
                'amount_paid' => $locked->grand_total,
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->recordActivity($locked, $actor, OrderStatusHistory::ACTION_UPI_PAYMENT_CONFIRMED, [
                'attempt_uuid' => $attempt->uuid,
                'attempt_sequence' => $attempt->sequence,
                'customer_submitted_reference' => $attempt->submitted_reference,
                'confirmed_reference' => $confirmedReference,
                'amount' => (string) $locked->grand_total,
                'actor_id' => $actor->getKey(),
                'confirmed_at' => $confirmedAt->toIso8601String(),
            ], 'Direct Merchant UPI payment confirmed.');

            return $locked->refresh();
        });
    }

    public function reject(Order $order, User $actor, string $reason): Order
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'upi_rejection_reason' => 'Please explain why the payment could not be verified.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertDirectUpi($locked);

            if ($locked->payment_status === Order::PAYMENT_PAID) {
                throw ValidationException::withMessages([
                    'upi_rejection_reason' => 'A confirmed payment cannot be marked as not verified.',
                ]);
            }

            if (! in_array($locked->payment_status, [Order::PAYMENT_PENDING, Order::PAYMENT_UNPAID], true)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'This payment is not in a state that can be marked as not verified.',
                ]);
            }

            $attempt = $this->attempts->currentSubmittedForUpdate($locked);
            $rejectedAt = now();
            $attempt->forceFill([
                'status' => DirectMerchantUpiAttempt::STATUS_REJECTED,
                'active_slot' => null,
                'rejected_at' => $rejectedAt,
                'rejected_by' => $actor->getKey(),
                'rejection_reason' => $reason,
            ])->save();
            $this->recordActivity($locked, $actor, OrderStatusHistory::ACTION_UPI_PAYMENT_REJECTED, [
                'attempt_uuid' => $attempt->uuid,
                'attempt_sequence' => $attempt->sequence,
                'customer_submitted_reference' => $attempt->submitted_reference,
                'reason' => $reason,
                'actor_id' => $actor->getKey(),
                'rejected_at' => $rejectedAt->toIso8601String(),
            ], 'Direct Merchant UPI payment could not be verified.');

            return $locked->refresh();
        });
    }

    private function assertDirectUpi(Order $order): void
    {
        if ($order->payment_method !== StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI) {
            throw ValidationException::withMessages([
                'payment_method' => 'This order does not use Direct Merchant UPI.',
            ]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function recordActivity(Order $order, User $actor, string $action, array $metadata, string $notes): void
    {
        $order->statusHistories()->create([
            'from_status' => $order->order_status,
            'to_status' => $order->order_status,
            'notes' => $notes,
            'changed_by' => $actor->getKey(),
            'metadata' => ['action' => $action, ...$metadata],
            'created_at' => now(),
        ]);
    }
}
