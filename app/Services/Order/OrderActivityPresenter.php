<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderExchange;
use App\Models\OrderRefund;
use App\Models\OrderStatusHistory;
use App\Services\Admin\AdminSettingsService;

class OrderActivityPresenter
{
    public function __construct(
        private readonly AdminSettingsService $settings,
    ) {}

    public function type(OrderStatusHistory $history): string
    {
        $action = $history->metadata['action'] ?? null;

        if ($action === OrderStatusHistory::ACTION_REFUND_PROCESSED || filled($history->metadata['refund_number'] ?? null)) {
            return 'refund';
        }

        if ($action === OrderStatusHistory::ACTION_EXCHANGE_PROCESSED || filled($history->metadata['exchange_number'] ?? null)) {
            return 'exchange';
        }

        if ($action === OrderStatusHistory::ACTION_UPI_PAYMENT_CONFIRMED) {
            return 'upi_payment_confirmed';
        }

        if ($action === OrderStatusHistory::ACTION_UPI_PAYMENT_REJECTED) {
            return 'upi_payment_rejected';
        }

        if ($action === OrderStatusHistory::ACTION_UPI_PAYMENT_EXPIRED) {
            return 'upi_payment_expired';
        }

        if ($action === OrderStatusHistory::ACTION_PICKUP_COLLECTION_EXPIRED) {
            return 'pickup_collection_expired';
        }

        return 'status';
    }

    public function title(OrderStatusHistory $history): ?string
    {
        return match ($this->type($history)) {
            'refund' => 'Refund Processed',
            'exchange' => 'Exchange Processed',
            'upi_payment_confirmed' => 'UPI Payment Confirmed',
            'upi_payment_rejected' => 'UPI Payment Could Not Be Verified',
            'upi_payment_expired' => 'UPI Payment Expired',
            'pickup_collection_expired' => 'Pickup Collection Window Expired',
            default => null,
        };
    }

    public function merchantDescription(Order $order, OrderStatusHistory $history): ?string
    {
        return match ($this->type($history)) {
            'refund' => $this->refundDescription($order, $history, false),
            'exchange' => $this->exchangeDescription($order, $history),
            'upi_payment_confirmed' => 'Confirmed '.$this->money($history->metadata['amount'] ?? $order->grand_total)
                .' with UPI reference '.($history->metadata['confirmed_reference'] ?? $order->upi_txn).'.',
            'upi_payment_rejected' => 'Payment could not be verified. Reason: '.($history->metadata['reason'] ?? 'Not provided').'.',
            'upi_payment_expired' => 'Direct UPI payment was not verified within '.((int) ($history->metadata['expiry_minutes'] ?? 0)).' minutes. The order was automatically cancelled.',
            'pickup_collection_expired' => 'The customer did not collect the order within the '.((int) ($history->metadata['expiry_hours'] ?? 0)).'-hour pickup collection window. The order was automatically cancelled.',
            default => $history->notes,
        };
    }

    public function customerDescription(Order $order, OrderStatusHistory $history): ?string
    {
        return match ($this->type($history)) {
            'refund' => $this->refundDescription($order, $history, true),
            'exchange' => $this->exchangeDescription($order, $history),
            'upi_payment_confirmed' => 'The merchant confirmed receipt of '.$this->money($history->metadata['amount'] ?? $order->grand_total).'.',
            'upi_payment_rejected' => 'The merchant could not verify the submitted payment reference. The order remains open while payment is resolved.',
            'upi_payment_expired' => 'The order was cancelled because the UPI payment was not verified within the allowed payment time.',
            'pickup_collection_expired' => 'The pickup collection window expired before the order was collected.',
            default => null,
        };
    }

    private function refundDescription(Order $order, OrderStatusHistory $history, bool $customer): string
    {
        $refund = $this->refund($order, $history);
        $amount = $refund?->refund_total ?? ($history->metadata['refund_total'] ?? 0);
        $items = $refund instanceof OrderRefund ? $this->refundItems($refund) : '';

        if ($customer) {
            return 'Refund of '.$this->money($amount).' processed'.($items !== '' ? ' for '.$items : '').'.';
        }

        return 'Refund processed'.($items !== '' ? ' for '.$items : '').' ('.$this->money($amount).').';
    }

    private function exchangeDescription(Order $order, OrderStatusHistory $history): string
    {
        $exchange = $this->exchange($order, $history);

        if (! $exchange instanceof OrderExchange) {
            return 'Exchange processed.';
        }

        $returned = $exchange->items
            ->map(fn ($item): string => $this->itemLabel($item->orderItem?->product_name, $item->orderItem?->variant_name, $item->quantity))
            ->filter()
            ->implode(', ');
        $replacement = $exchange->replacementOrder?->items
            ->map(fn ($item): string => $this->itemLabel($item->product_name, $item->variant_name, $item->quantity))
            ->filter()
            ->implode(', ') ?? '';

        if ($returned !== '' && $replacement !== '') {
            return $returned.' exchanged for '.$replacement.'.';
        }

        return 'Exchange processed'.($returned !== '' ? ' for '.$returned : '').'.';
    }

    private function refund(Order $order, OrderStatusHistory $history): ?OrderRefund
    {
        $refundId = (int) ($history->metadata['refund_id'] ?? 0);
        $refundNumber = $history->metadata['refund_number'] ?? null;

        return $order->refunds->first(
            fn (OrderRefund $refund): bool => ($refundId > 0 && (int) $refund->getKey() === $refundId)
                || ($refundNumber !== null && $refund->refund_number === $refundNumber),
        );
    }

    private function exchange(Order $order, OrderStatusHistory $history): ?OrderExchange
    {
        $exchangeId = (int) ($history->metadata['exchange_id'] ?? 0);
        $exchangeNumber = $history->metadata['exchange_number'] ?? null;

        return $order->exchanges->first(
            fn (OrderExchange $exchange): bool => ($exchangeId > 0 && (int) $exchange->getKey() === $exchangeId)
                || ($exchangeNumber !== null && $exchange->exchange_number === $exchangeNumber),
        );
    }

    private function refundItems(OrderRefund $refund): string
    {
        return $refund->items
            ->map(fn ($item): string => $this->itemLabel($item->orderItem?->product_name, $item->orderItem?->variant_name, $item->quantity))
            ->filter()
            ->implode(', ');
    }

    private function itemLabel(?string $product, ?string $variant, float|int|string $quantity): string
    {
        $name = trim((string) $product);
        $variant = trim((string) $variant);

        if ($variant !== '' && strcasecmp($variant, 'Default') !== 0 && strcasecmp($variant, $name) !== 0) {
            $name .= ' - '.$variant;
        }

        return trim($name).' x '.$this->quantity($quantity);
    }

    private function quantity(float|int|string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');
    }

    private function money(float|int|string $value): string
    {
        $currency = $this->settings->currencyConfig();
        $amount = number_format(
            (float) $value,
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
    }
}
