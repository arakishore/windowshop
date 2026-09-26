<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CustomerRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly string $occurrenceId) {}
}
