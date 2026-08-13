<?php

namespace App\Providers;

use App\Services\DateTime\DateDisplayService;
use App\Services\Marketplace\MarketplaceLogoService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DateDisplayService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('storefront.partials.header', function ($view): void {
            static $marketplaceLogoUrl = null;

            $marketplaceLogoUrl ??= app(MarketplaceLogoService::class)->url();

            $view->with('marketplaceLogoUrl', $marketplaceLogoUrl);
        });
    }
}
