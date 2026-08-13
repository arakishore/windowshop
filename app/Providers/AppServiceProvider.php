<?php

namespace App\Providers;

use App\Services\DateTime\DateDisplayService;
use App\Services\Marketplace\MarketplaceLogoService;
use App\Services\Storefront\CustomerLocationService;
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
        $this->app->singleton(CustomerLocationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer(['storefront.partials.header', 'storefront.partials.customer-location-modal'], function ($view): void {
            static $marketplaceLogoUrl = null;

            $marketplaceLogoUrl ??= app(MarketplaceLogoService::class)->url();
            $location = app(CustomerLocationService::class);
            $currentPostalCode = $location->postalCode();

            $view->with([
                'marketplaceLogoUrl' => $marketplaceLogoUrl,
                'currentPostalCode' => $currentPostalCode,
                'shouldAutoOpenCustomerLocationModal' => $currentPostalCode === null,
            ]);
        });
    }
}
