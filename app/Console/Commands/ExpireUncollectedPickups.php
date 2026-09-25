<?php

namespace App\Console\Commands;

use App\Services\Order\ExpireUncollectedPickupsService;
use Illuminate\Console\Command;

class ExpireUncollectedPickups extends Command
{
    protected $signature = 'orders:expire-uncollected-pickups';

    protected $description = 'Cancel eligible Cash at Shop pickup orders whose collection window has expired.';

    public function handle(ExpireUncollectedPickupsService $expiry): int
    {
        $counts = $expiry->run();

        $this->info("Candidates checked: {$counts['candidates']}");
        $this->info("Orders expired: {$counts['expired']}");
        $this->info("Orders skipped after recheck: {$counts['skipped']}");

        return self::SUCCESS;
    }
}
