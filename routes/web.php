<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MasterData\BrandController;
use App\Http\Controllers\Admin\MasterData\ProductAttributeGroupController;
use App\Http\Controllers\Admin\MasterData\ProductAttributeGroupValueController;
use App\Http\Controllers\Admin\MasterData\ProductCategoryAttributeGroupController;
use App\Http\Controllers\Admin\MasterData\ProductCategoryController;
use App\Http\Controllers\Admin\MasterData\ProductDescriptionTemplateController;
use App\Http\Controllers\Admin\MasterData\ShopAudienceController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MerchantShopController;
use App\Http\Controllers\Admin\ProductController;

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
        Route::resource('master/shop-audiences', ShopAudienceController::class)
            ->except(['show'])
            ->names('master.shop-audiences');
        Route::resource('master/brands', BrandController::class)
            ->except(['show'])
            ->names('master.brands');
        Route::get('master/product-categories/{productCategory}/attribute-groups', [ProductCategoryAttributeGroupController::class, 'edit'])
            ->name('master.product-categories.attribute-groups.edit');
        Route::put('master/product-categories/{productCategory}/attribute-groups', [ProductCategoryAttributeGroupController::class, 'update'])
            ->name('master.product-categories.attribute-groups.update');
        Route::resource('master/product-categories', ProductCategoryController::class)
            ->parameters(['product-categories' => 'productCategory'])
            ->names('master.product-categories');
        Route::resource('master/product-attributes', ProductAttributeGroupController::class)
            ->except(['show'])
            ->parameters(['product-attributes' => 'productAttribute'])
            ->names('master.product-attributes');
        Route::resource('master/product-attributes/{productAttribute}/values', ProductAttributeGroupValueController::class)
            ->except(['show'])
            ->parameters(['values' => 'productAttributeGroupValue'])
            ->names('master.product-attributes.values');
        Route::get('master/description-templates/{description_template}/preview', [ProductDescriptionTemplateController::class, 'preview'])
            ->name('master.description-templates.preview');
        Route::post('master/description-templates/{description_template}/preview', [ProductDescriptionTemplateController::class, 'generatePreview'])
            ->name('master.description-templates.preview.generate');
        Route::resource('master/description-templates', ProductDescriptionTemplateController::class)
            ->except(['show'])
            ->names('master.description-templates');
        Route::get('/merchants/{merchant}/address', [MerchantController::class, 'address'])->name('merchants.address');
        Route::post('/merchants/{merchant}/address', [MerchantController::class, 'updateAddress'])->name('merchants.address.update');
        Route::get('/merchants/address/states', [MerchantController::class, 'addressStates'])->name('merchants.address.states');
        Route::get('/merchants/address/cities', [MerchantController::class, 'addressCities'])->name('merchants.address.cities');
        Route::resource('/merchants/{merchant}/shops', MerchantShopController::class)
            ->names('merchants.shops');
        Route::resource('merchants', MerchantController::class);
        Route::put('products/{product}/attributes', [ProductController::class, 'updateAttributes'])
            ->name('products.attributes.update');
        Route::post('products/{product}/variants/generate', [ProductController::class, 'generateVariants'])
            ->name('products.variants.generate');
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
