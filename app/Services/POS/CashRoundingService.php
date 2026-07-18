<?php

namespace App\Services\POS;

class CashRoundingService
{
    /**
     * @param array{method?: string, applyTo?: array<int, string>|string} $settings
     */
    public function adjustment(float|int|string $amount, string $paymentMethod, array $settings): float
    {
        if (! $this->appliesTo($paymentMethod, $settings['applyTo'] ?? [])) {
            return 0.0;
        }

        $amount = round((float) $amount, 2);
        $rounded = match ($settings['method'] ?? 'nearest') {
            'up' => ceil($amount),
            'down' => floor($amount),
            default => round($amount),
        };

        return round($rounded - $amount, 2);
    }

    /**
     * @param array{method?: string, applyTo?: array<int, string>|string} $settings
     */
    public function total(float|int|string $amount, string $paymentMethod, array $settings): float
    {
        $amount = round((float) $amount, 2);

        return round($amount + $this->adjustment($amount, $paymentMethod, $settings), 2);
    }

    /**
     * @param array<int, string>|string $applyTo
     */
    private function appliesTo(string $paymentMethod, array|string $applyTo): bool
    {
        $methods = is_array($applyTo) ? $applyTo : array_filter(explode(',', $applyTo));

        return in_array('all', $methods, true) || in_array($paymentMethod, $methods, true);
    }
}
