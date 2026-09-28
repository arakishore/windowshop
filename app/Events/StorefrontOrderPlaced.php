<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class StorefrontOrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order, public readonly string $occurrenceId) {}
}
