<?php

namespace App\Services\Order;

use App\Models\Customer;
use App\Models\DirectMerchantUpiAttempt;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Events\DirectMerchantUpiLifecycle;
use App\Services\Checkout\StorefrontPaymentMethodService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectMerchantUpiAttemptService
{
    public function createInitial(Order $order, User $actor, string $reference): DirectMerchantUpiAttempt
    {
        $reference = $this->validatedReference($reference);
        $this->assertDirectUpi($order);

        $attempt = $this->createSubmitted($order, $actor, $reference, 1);
        DirectMerchantUpiLifecycle::dispatch($order, 'submitted', "upi.submitted:{$attempt->uuid}");
        return $attempt;
    }

    public function submitCorrection(Order $order, Customer $customer, User $actor, string $reference): DirectMerchantUpiAttempt
    {
        $reference = $this->validatedReference($reference);

        return DB::transaction(function () use ($order, $customer, $actor, $reference): DirectMerchantUpiAttempt {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertDirectUpi($locked);

            if ((int) $locked->customer_id !== (int) $customer->getKey()) {
                abort(404);
            }

            if ($locked->payment_status === Order::PAYMENT_PAID) {
                throw ValidationException::withMessages(['upi_reference' => 'This payment has already been verified.']);
            }

            if (in_array($locked->order_status, [Order::STATUS_CANCELLED, Order::STATUS_COMPLETED], true)) {
                throw ValidationException::withMessages(['upi_reference' => 'A reference cannot be submitted for a cancelled or completed order.']);
            }

            if ($this->submittedQuery($locked)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['upi_reference' => 'A UPI reference is already awaiting merchant verification.']);
            }

            $latest = $locked->directMerchantUpiAttempts()->lockForUpdate()->orderByDesc('sequence')->first();
            if (! $latest instanceof DirectMerchantUpiAttempt || $latest->status !== DirectMerchantUpiAttempt::STATUS_REJECTED) {
                throw ValidationException::withMessages(['upi_reference' => 'A corrected reference can be submitted only after the previous reference is rejected.']);
            }

            $attempt = $this->createSubmitted($locked, $actor, $reference, $latest->sequence + 1);
            $locked->forceFill([
                'payment_reference' => $reference,
                'upi_txn' => null,
                'updated_by' => $actor->getKey(),
            ])->save();
            DirectMerchantUpiLifecycle::dispatch($locked, 'submitted', "upi.submitted:{$attempt->uuid}");

            return $attempt;
        });
    }

    public function currentSubmittedForUpdate(Order $order): DirectMerchantUpiAttempt
    {
        $attempt = $this->submittedQuery($order)->lockForUpdate()->first();

        if ($attempt instanceof DirectMerchantUpiAttempt) {
            return $attempt;
        }

        if ($order->directMerchantUpiAttempts()->exists() || $this->hasLegacyRejection($order)) {
            throw ValidationException::withMessages(['payment_status' => 'There is no submitted UPI reference awaiting verification.']);
        }

        $reference = trim((string) $order->payment_reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['payment_status' => 'There is no submitted UPI reference awaiting verification.']);
        }

        return $this->createSubmitted(
            $order,
            $order->createdBy,
            $this->validatedReference($reference),
            1,
            $order->created_at,
        );
    }

    public function canSubmitCorrection(Order $order): bool
    {
        if ($order->payment_method !== StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI
            || $order->payment_status === Order::PAYMENT_PAID
            || in_array($order->order_status, [Order::STATUS_CANCELLED, Order::STATUS_COMPLETED], true)) {
            return false;
        }

        if ($order->directMerchantUpiAttempts->contains('status', DirectMerchantUpiAttempt::STATUS_SUBMITTED)) {
            return false;
        }

        return $order->directMerchantUpiAttempts->sortByDesc('sequence')->first()?->status === DirectMerchantUpiAttempt::STATUS_REJECTED;
    }

    public function validatedReference(string $reference, string $field = 'upi_reference'): string
    {
        $reference = trim($reference);

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{3,99}$/', $reference)) {
            throw ValidationException::withMessages([
                $field => 'Enter a valid UPI transaction reference using 4 to 100 letters, numbers, dots, underscores, or hyphens.',
            ]);
        }

        return $reference;
    }

    private function createSubmitted(Order $order, ?User $actor, string $reference, int $sequence, mixed $submittedAt = null): DirectMerchantUpiAttempt
    {
        try {
            return $order->directMerchantUpiAttempts()->create([
                'sequence' => $sequence,
                'submitted_reference' => $reference,
                'status' => DirectMerchantUpiAttempt::STATUS_SUBMITTED,
                'active_slot' => 1,
                'submitted_at' => $submittedAt ?? now(),
                'submitted_by' => $actor?->getKey(),
            ]);
        } catch (QueryException $exception) {
            if ((string) ($exception->errorInfo[0] ?? '') !== '23000') {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'upi_reference' => 'A UPI reference is already awaiting merchant verification.',
            ]);
        }
    }

    private function submittedQuery(Order $order)
    {
        return $order->directMerchantUpiAttempts()
            ->where('status', DirectMerchantUpiAttempt::STATUS_SUBMITTED)
            ->where('active_slot', 1);
    }

    private function assertDirectUpi(Order $order): void
    {
        if ($order->payment_method !== StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI) {
            throw ValidationException::withMessages(['payment_method' => 'This order does not use Direct Merchant UPI.']);
        }
    }

    private function hasLegacyRejection(Order $order): bool
    {
        return $order->statusHistories()
            ->where('metadata->action', OrderStatusHistory::ACTION_UPI_PAYMENT_REJECTED)
            ->exists();
    }
}
