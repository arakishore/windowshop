<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class OrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly Order $order,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly string $occurrenceId,
        public readonly array $metadata = [],
    ) {}
}
