<?php

use App\Models\Product;
use App\Services\Product\ProductPurgeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('products:purge-trash', function (): int {
    $productPurgeService = app(ProductPurgeService::class);
    $purged = 0;

    Product::onlyTrashed()
        ->where('deleted_at', '<=', now()->subDays(45))
        ->orderBy('id')
        ->chunkById(100, function ($products) use ($productPurgeService, &$purged): void {
            foreach ($products as $product) {
                $productPurgeService->purge($product);
                $purged++;
            }
        });

    $this->info("Purged {$purged} product(s) from trash.");

    return 0;
})->purpose('Permanently delete products that have been in trash for more than 45 days');

Schedule::command('products:purge-trash')
    ->dailyAt('02:00')
    ->withoutOverlapping();
