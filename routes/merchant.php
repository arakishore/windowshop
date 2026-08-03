<?php

use App\Http\Controllers\Merchant\Auth\MerchantAuthController;
use App\Http\Controllers\Merchant\Auth\MerchantPasswordController;
use App\Http\Controllers\Merchant\Auth\MerchantProfileController;
use App\Http\Controllers\Merchant\BarcodeLabelController;
use App\Http\Controllers\Merchant\AvailabilityStatusController;
use App\Http\Controllers\Merchant\CatalogueMasterController;
use App\Http\Controllers\Merchant\CancellationReasonController;
use App\Http\Controllers\Merchant\CustomerController;
use App\Http\Controllers\Merchant\CustomerAddressController;
use App\Http\Controllers\Merchant\MerchantDetailsController;
use App\Http\Controllers\Merchant\MerchantSettingsController;
use App\Http\Controllers\Merchant\MerchantShopController;
use App\Http\Controllers\Merchant\MerchantShopContextController;
use App\Http\Controllers\Merchant\MerchantTaxSettingController;
use App\Http\Controllers\Merchant\PosController;
use App\Http\Controllers\Merchant\ProductController;
use App\Http\Controllers\Merchant\ReturnReasonController;
use App\Http\Controllers\Merchant\SalesHistoryController;
use App\Http\Controllers\Merchant\TaxSlabController;
use Illuminate\Support\Facades\Route;

Route::prefix('merchant')->name('merchant.')->group(function (): void {

    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [MerchantAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [MerchantAuthController::class, 'authenticate'])->name('authenticate');
    });

    Route::middleware(['auth', 'merchant.role'])->group(function (): void {
        Route::get('/profile', [MerchantProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [MerchantProfileController::class, 'update'])->name('profile.update');
        Route::get('/details', [MerchantDetailsController::class, 'edit'])->name('details.edit');
        Route::put('/details', [MerchantDetailsController::class, 'update'])->name('details.update');
        Route::get('/settings', [MerchantSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [MerchantSettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/tax', [MerchantTaxSettingController::class, 'edit'])->name('tax-settings.edit');
        Route::put('/settings/tax', [MerchantTaxSettingController::class, 'update'])->name('tax-settings.update');
        Route::delete('/settings/tax/{taxSetting}', [MerchantTaxSettingController::class, 'destroy'])->name('tax-settings.destroy');
        Route::get('/settings/tax-slabs', [TaxSlabController::class, 'index'])->name('tax-slabs.index');
        Route::get('/change-password', [MerchantPasswordController::class, 'edit'])->name('password.edit');
        Route::put('/change-password', [MerchantPasswordController::class, 'update'])->name('password.update');
        Route::post('/active-shop', [MerchantShopContextController::class, 'update'])->name('active-shop.update');
        Route::get('/shops', [MerchantShopController::class, 'index'])->name('shops.index');
        Route::get('/shops/create', [MerchantShopController::class, 'create'])->name('shops.create');
        Route::post('/shops', [MerchantShopController::class, 'store'])->name('shops.store');
        Route::get('/shops/{shop}', [MerchantShopController::class, 'show'])->name('shops.show');
        Route::get('/shops/{shop}/edit', [MerchantShopController::class, 'edit'])->name('shops.edit');
        Route::put('/shops/{shop}', [MerchantShopController::class, 'update'])->name('shops.update');
        Route::post('/shops/{shop}/activate', [MerchantShopController::class, 'activate'])->name('shops.activate');
        Route::get('/logout', [MerchantAuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth', 'merchant.role', 'merchant.active_shop'])->group(function (): void {
        Route::get('/dashboard', [MerchantAuthController::class, 'dashboard'])->name('dashboard');
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
        Route::post('/pos/pricing', [PosController::class, 'pricing'])->name('pos.pricing');
        Route::get('/pos/customers', [PosController::class, 'customers'])->name('pos.customers');
        Route::post('/pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');
        Route::get('/pos/customers/{customer}/addresses', [PosController::class, 'customerAddresses'])->name('pos.customers.addresses');
        Route::post('/pos/customers/{customer}/addresses', [PosController::class, 'storeCustomerAddress'])->name('pos.customers.addresses.store');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/recent-sales', [PosController::class, 'recentSales'])->name('pos.recent-sales');
        Route::get('/pos/orders/{order}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
        Route::get('/catalogue/masters', [CatalogueMasterController::class, 'index'])->name('catalogue-masters.index');
        Route::post('/catalogue/masters/requests', [CatalogueMasterController::class, 'store'])->name('catalogue-masters.requests.store');
        Route::get('/sales', [SalesHistoryController::class, 'index'])->name('sales.index');
        Route::get('/sales/{order}', [SalesHistoryController::class, 'show'])->name('sales.show');
        Route::get('/sales/{order}/refund', [SalesHistoryController::class, 'refund'])->name('sales.refund');
        Route::post('/sales/{order}/refund', [SalesHistoryController::class, 'processRefund'])->name('sales.refund.process');
        Route::get('/sales/{order}/exchange', [SalesHistoryController::class, 'exchange'])->name('sales.exchange');
        Route::post('/sales/{order}/exchange', [SalesHistoryController::class, 'processExchange'])->name('sales.exchange.process');
        Route::get('/sales/exchanges/{exchange}/receipt', [SalesHistoryController::class, 'exchangeReceipt'])->name('sales.exchange.receipt');
        Route::resource('return-reasons', ReturnReasonController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('cancellation-reasons/trash', [CancellationReasonController::class, 'trash'])
            ->name('cancellation-reasons.trash');
        Route::post('cancellation-reasons/{cancellationReason}/restore', [CancellationReasonController::class, 'restore'])
            ->name('cancellation-reasons.restore');
        Route::resource('cancellation-reasons', CancellationReasonController::class)
            ->except(['show']);
        Route::post('availability-statuses/{availabilityStatus}/restore', [AvailabilityStatusController::class, 'restore'])
            ->name('availability-statuses.restore');
        Route::resource('availability-statuses', AvailabilityStatusController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::get('/barcodes/labels', [BarcodeLabelController::class, 'index'])->name('barcodes.labels.index');
        Route::post('/barcodes/generate-missing', [BarcodeLabelController::class, 'generateMissing'])->name('barcodes.generate-missing');
        Route::post('/barcodes/labels/print', [BarcodeLabelController::class, 'print'])->name('barcodes.labels.print');
        Route::post('customers/bulk-action', [CustomerController::class, 'bulkAction'])
            ->name('customers.bulk-action');
        Route::get('customers/mobile-lookup', [CustomerController::class, 'mobileLookup'])
            ->name('customers.mobile-lookup');
        Route::get('customer-addresses/states', [CustomerAddressController::class, 'states'])
            ->name('customer-addresses.states');
        Route::get('customer-addresses/cities', [CustomerAddressController::class, 'cities'])
            ->name('customer-addresses.cities');
        Route::resource('customers.addresses', CustomerAddressController::class)
            ->except(['index', 'show']);
        Route::resource('customers', CustomerController::class);
        Route::post('products/bulk-action', [ProductController::class, 'bulkAction'])
            ->name('products.bulk-action');
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])
            ->name('products.duplicate');
        Route::post('products/{product}/archive', [ProductController::class, 'archive'])
            ->name('products.archive');
        Route::post('products/{product}/restore-archive', [ProductController::class, 'restoreArchive'])
            ->name('products.restore-archive');
        Route::put('products/{product}/attributes', [ProductController::class, 'updateAttributes'])
            ->name('products.attributes.update');
        Route::post('products/{product}/images', [ProductController::class, 'storeImages'])
            ->name('products.images.store');
        Route::put('products/{product}/images', [ProductController::class, 'updateImages'])
            ->name('products.images.update');
        Route::delete('products/{product}/images', [ProductController::class, 'bulkDestroyImages'])
            ->name('products.images.bulk-destroy');
        Route::delete('products/{product}/images/{productImage}', [ProductController::class, 'destroyImage'])
            ->name('products.images.destroy');
        Route::post('products/{product}/variants/generate', [ProductController::class, 'generateVariants'])
            ->name('products.variants.generate');
        Route::delete('products/{product}/variants/stale', [ProductController::class, 'removeStaleVariants'])
            ->name('products.variants.stale-destroy');
        Route::post('products/{product}/barcodes/generate', [ProductController::class, 'generateBarcodes'])
            ->name('products.barcodes.generate');
        Route::put('products/{product}/variants', [ProductController::class, 'updateVariants'])
            ->name('products.variants.update');
        Route::put('products/{product}/variants/bulk', [ProductController::class, 'bulkUpdateVariants'])
            ->name('products.variants.bulk-update');
        Route::put('products/{product}/description-seo', [ProductController::class, 'updateDescriptionSeo'])
            ->name('products.description-seo.update');
        Route::post('products/{product}/description-seo/generate', [ProductController::class, 'generateDescriptionSeo'])
            ->name('products.description-seo.generate');
        Route::resource('products', ProductController::class)
            ->except(['show']);
    });

});
