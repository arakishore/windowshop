<?php

namespace App\Events;

use App\Models\MerchantProfile;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class MerchantAccountCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly MerchantProfile $merchant, public readonly string $occurrenceId) {}
}
