<?php

use App\Http\Controllers\Merchant\Auth\MerchantAuthController;
use App\Http\Controllers\Merchant\Auth\MerchantPasswordController;
use App\Http\Controllers\Merchant\Auth\MerchantProfileController;
use App\Http\Controllers\Merchant\BarcodeLabelController;
use App\Http\Controllers\Merchant\MerchantDetailsController;
use App\Http\Controllers\Merchant\MerchantShopController;
use App\Http\Controllers\Merchant\MerchantShopContextController;
use App\Http\Controllers\Merchant\PosController;
use App\Http\Controllers\Merchant\ProductController;
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
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/recent-sales', [PosController::class, 'recentSales'])->name('pos.recent-sales');
        Route::get('/pos/orders/{order}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
        Route::get('/barcodes/labels', [BarcodeLabelController::class, 'index'])->name('barcodes.labels.index');
        Route::post('/barcodes/generate-missing', [BarcodeLabelController::class, 'generateMissing'])->name('barcodes.generate-missing');
        Route::post('/barcodes/labels/print', [BarcodeLabelController::class, 'print'])->name('barcodes.labels.print');
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
