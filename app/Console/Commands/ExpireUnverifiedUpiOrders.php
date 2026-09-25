<?php

namespace App\Console\Commands;

use App\Services\Order\ExpireUnverifiedUpiOrdersService;
use Illuminate\Console\Command;

class ExpireUnverifiedUpiOrders extends Command
{
    protected $signature = 'orders:expire-unverified-upi';

    protected $description = 'Cancel eligible storefront Direct Merchant UPI orders whose verification window has expired.';

    public function handle(ExpireUnverifiedUpiOrdersService $expiry): int
    {
        $counts = $expiry->run();

        $this->info("Candidates checked: {$counts['candidates']}");
        $this->info("Orders expired: {$counts['expired']}");
        $this->info("Orders skipped after recheck: {$counts['skipped']}");

        return self::SUCCESS;
    }
}
