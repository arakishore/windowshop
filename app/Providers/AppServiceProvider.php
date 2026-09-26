<?php

namespace App\Providers;

use App\Services\DateTime\DateDisplayService;
use App\Services\Marketplace\MarketplaceLogoService;
use App\Services\Cart\CartResolver;
use App\Services\Cart\CartPageService;
use App\Services\Storefront\CustomerLocationService;
use App\Services\System\SystemSettingService;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\NotificationChannelRegistry;
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
        $this->app->singleton(SystemSettingService::class);
        $this->app->singleton(NotificationChannelRegistry::class, fn ($app) => new NotificationChannelRegistry([
            $app->make(EmailChannel::class),
            $app->make(SmsChannel::class),
            $app->make(WhatsAppChannel::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer([
            'storefront.*',
            'admin.*',
            'merchant.*',
            'layouts.*',
            'partials.*',
            'components.*',
            'shared.*',
        ], function ($view): void {
            static $marketplaceName = null;

            $marketplaceName ??= app(SystemSettingService::class)->marketplaceName();

            $view->with('marketplaceName', $marketplaceName);
        });

        View::composer([
            'storefront.partials.header',
            'storefront.layouts.app',
            'storefront.partials.search',
            'storefront.partials.customer-location-modal',
            'storefront.pages.product-detail',
        ], function ($view): void {
            static $marketplaceLogoUrl = null;

            $marketplaceLogoUrl ??= app(MarketplaceLogoService::class)->url();
            $location = app(CustomerLocationService::class);
            $currentPostalCode = $location->postalCode();

            $view->with([
                'marketplaceLogoUrl' => $marketplaceLogoUrl,
                'currentPostalCode' => $currentPostalCode,
                'shouldAutoOpenCustomerLocationModal' => $currentPostalCode === null,
                'storefrontCartCount' => app(CartResolver::class)->itemCount(request()),
                'storefrontMiniCart' => app(CartPageService::class)->pageData(request()),
            ]);
        });
    }
}
