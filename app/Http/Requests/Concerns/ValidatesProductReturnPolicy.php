<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesProductReturnPolicy
{
    /**
     * @return array<string, mixed>
     */
    protected function returnPolicyRules(): array
    {
        return [
            'return_policy' => ['nullable', 'array'],
            'return_policy.refund' => ['nullable', Rule::in(['inherit', 'allowed', 'not_allowed'])],
            'return_policy.refund_window_days' => ['nullable', 'integer', 'min:0'],
            'return_policy.exchange' => ['nullable', Rule::in(['inherit', 'allowed', 'not_allowed'])],
            'return_policy.exchange_window_days' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{refund_allowed: ?bool, refund_window_days: ?int, exchange_allowed: ?bool, exchange_window_days: ?int}
     */
    public function returnPolicyConfiguration(): array
    {
        $refund = $this->policyPair(
            (string) $this->input('return_policy.refund', 'inherit'),
            $this->input('return_policy.refund_window_days'),
        );
        $exchange = $this->policyPair(
            (string) $this->input('return_policy.exchange', 'inherit'),
            $this->input('return_policy.exchange_window_days'),
        );

        return [
            'refund_allowed' => $refund['allowed'],
            'refund_window_days' => $refund['window_days'],
            'exchange_allowed' => $exchange['allowed'],
            'exchange_window_days' => $exchange['window_days'],
        ];
    }

    /**
     * @return array{allowed: ?bool, window_days: ?int}
     */
    private function policyPair(string $mode, mixed $windowDays): array
    {
        return match ($mode) {
            'allowed' => [
                'allowed' => true,
                'window_days' => max(0, (int) ($windowDays ?? 0)),
            ],
            'not_allowed' => [
                'allowed' => false,
                'window_days' => 0,
            ],
            default => [
                'allowed' => null,
                'window_days' => null,
            ],
        };
    }
}
