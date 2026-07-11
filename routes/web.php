<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MerchantShopController;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'authenticate'])->name('authenticate');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth', 'admin.role'])->group(function () {
        Route::get('/merchants/{merchant}/address', [MerchantController::class, 'address'])->name('merchants.address');
        Route::post('/merchants/{merchant}/address', [MerchantController::class, 'updateAddress'])->name('merchants.address.update');
        Route::get('/merchants/address/states', [MerchantController::class, 'addressStates'])->name('merchants.address.states');
        Route::get('/merchants/address/cities', [MerchantController::class, 'addressCities'])->name('merchants.address.cities');
        Route::resource('/merchants/{merchant}/shops', MerchantShopController::class)
            ->names('merchants.shops');
        Route::resource('merchants', MerchantController::class);
    });

});
