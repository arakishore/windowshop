<?php

use App\Http\Controllers\Merchant\Auth\MerchantAuthController;
use App\Http\Controllers\Merchant\Auth\MerchantPasswordController;
use App\Http\Controllers\Merchant\Auth\MerchantProfileController;
use App\Http\Controllers\Merchant\MerchantDetailsController;
use App\Http\Controllers\Merchant\MerchantShopController;
use App\Http\Controllers\Merchant\MerchantShopContextController;
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
    });

});
