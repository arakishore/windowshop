<?php

use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BannerLibraryController;
use App\Http\Controllers\Admin\BannerTemplateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MasterData\BrandController;
use App\Http\Controllers\Admin\MasterData\CatalogueMasterRequestController;
use App\Http\Controllers\Admin\MasterData\CustomerCancellationReasonController;
use App\Http\Controllers\Admin\MasterData\OrderStatusController;
use App\Http\Controllers\Admin\MasterData\PaymentStatusController;
use App\Http\Controllers\Admin\MasterData\PostalCodeRestrictionController;
use App\Http\Controllers\Admin\MasterData\PostalCodeController;
use App\Http\Controllers\Admin\MasterData\ProductAttributeGroupController;
use App\Http\Controllers\Admin\MasterData\ProductAttributeGroupValueController;
use App\Http\Controllers\Admin\MasterData\ProductCategoryAttributeGroupController;
use App\Http\Controllers\Admin\MasterData\ProductCategoryController;
use App\Http\Controllers\Admin\MasterData\ProductDescriptionTemplateController;
use App\Http\Controllers\Admin\MasterData\ShopAudienceController;
use App\Http\Controllers\Admin\MasterData\TaxClassController;
use App\Http\Controllers\Admin\MasterData\TaxRateComponentController;
use App\Http\Controllers\Admin\MasterData\TaxRateController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MerchantShopController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Customer\Auth\CustomerAuthController;
use App\Http\Controllers\Storefront\AccountAddressController;
use App\Http\Controllers\Storefront\CartItemController;
use App\Http\Controllers\Storefront\CheckoutAddressController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CouponController;
use App\Http\Controllers\Storefront\CustomerAccountController;
use App\Http\Controllers\Storefront\CustomerLocationController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/storefront', [StorefrontController::class, 'home'])->name('storefront.home');
Route::post('/location/postal-code', [CustomerLocationController::class, 'store'])->name('storefront.location.postal-code.store');
Route::post('/location/detect', [CustomerLocationController::class, 'detect'])->name('storefront.location.detect');
Route::get('/about-us', [StorefrontController::class, 'about'])->name('storefront.about');
Route::get('/stores', [StorefrontController::class, 'stores'])->name('storefront.stores');
Route::get('/testimonials', [StorefrontController::class, 'testimonials'])->name('storefront.testimonials');
Route::get('/faq', [StorefrontController::class, 'faq'])->name('storefront.faq');
Route::get('/terms-and-conditions', [StorefrontController::class, 'terms'])->name('storefront.terms');
Route::get('/privacy-policy', [StorefrontController::class, 'privacy'])->name('storefront.privacy');
Route::get('/return-and-refund', [StorefrontController::class, 'returns'])->name('storefront.returns');
Route::get('/shipping', [StorefrontController::class, 'shipping'])->name('storefront.shipping');
Route::get('/contact-us', [StorefrontController::class, 'contact'])->name('storefront.contact');
Route::get('/login', [StorefrontController::class, 'login'])->name('storefront.login');
Route::post('/login', [CustomerAuthController::class, 'login'])->name('storefront.login.store');
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('storefront.logout');
Route::get('/register', [StorefrontController::class, 'register'])->name('storefront.register');
Route::post('/register', [CustomerAuthController::class, 'register'])->name('storefront.register.store');
Route::get('/account', [CustomerAccountController::class, 'dashboard'])->name('storefront.account');
Route::get('/account/profile', [CustomerAccountController::class, 'profile'])->name('storefront.account.profile');
Route::put('/account/profile', [CustomerAccountController::class, 'updateProfile'])->name('storefront.account.profile.update');
Route::get('/account/addresses', [AccountAddressController::class, 'index'])->name('storefront.account.addresses');
Route::get('/account/addresses/create', [AccountAddressController::class, 'create'])->name('storefront.account.addresses.create');
Route::post('/account/addresses', [AccountAddressController::class, 'store'])->name('storefront.account.addresses.store');
Route::get('/account/addresses/states', [AccountAddressController::class, 'states'])->name('storefront.account.addresses.states');
Route::get('/account/addresses/cities', [AccountAddressController::class, 'cities'])->name('storefront.account.addresses.cities');
Route::get('/account/addresses/{address}/edit', [AccountAddressController::class, 'edit'])->name('storefront.account.addresses.edit');
Route::put('/account/addresses/{address}', [AccountAddressController::class, 'update'])->name('storefront.account.addresses.update');
Route::delete('/account/addresses/{address}', [AccountAddressController::class, 'destroy'])->name('storefront.account.addresses.destroy');
Route::post('/account/addresses/{address}/default-delivery', [AccountAddressController::class, 'defaultDelivery'])->name('storefront.account.addresses.default-delivery');
Route::post('/account/addresses/{address}/default-billing', [AccountAddressController::class, 'defaultBilling'])->name('storefront.account.addresses.default-billing');
Route::get('/account/orders', [CustomerAccountController::class, 'orders'])->name('storefront.account.orders');
Route::get('/account/orders/{order}', [CustomerAccountController::class, 'orderDetail'])->name('storefront.account.orders.show');
Route::post('/account/orders/{order}/cancel', [CustomerAccountController::class, 'cancelOrder'])->name('storefront.account.orders.cancel');
Route::get('/account/wishlist', [CustomerAccountController::class, 'wishlist'])->name('storefront.account.wishlist');
Route::get('/forgot-password', [StorefrontController::class, 'forgotPassword'])->name('storefront.forgot-password');
Route::view('/demo/shopping-bag-box', 'storefront.pages.demo-shopping-bag-box')->name('storefront.demo.shopping-bag-box');
Route::get('/products', [StorefrontController::class, 'products'])->name('storefront.products');
Route::post('/wishlist/products/{product}', [WishlistController::class, 'store'])->name('storefront.wishlist.products.store');
Route::delete('/wishlist/products/{product}', [WishlistController::class, 'destroy'])->name('storefront.wishlist.products.destroy');
Route::post('/cart/items', [CartItemController::class, 'store'])->name('storefront.cart.items.store');
Route::patch('/cart/items/{cartItem}', [CartItemController::class, 'update'])->name('storefront.cart.items.update');
Route::delete('/cart/items/{cartItem}', [CartItemController::class, 'destroy'])->name('storefront.cart.items.destroy');
Route::post('/cart/shops/{shop}/coupon', [CouponController::class, 'store'])->name('storefront.cart.shops.coupon.store');
Route::delete('/cart/shops/{shop}/coupon', [CouponController::class, 'destroy'])->name('storefront.cart.shops.coupon.destroy');
Route::post('/products/{slug}/delivery-check', [StorefrontController::class, 'checkProductDelivery'])->name('storefront.product.delivery-check');
Route::get('/category/{categoryPath}/products/{slug}', [StorefrontController::class, 'productDetailWithCategory'])
    ->where('categoryPath', '.+')
    ->name('storefront.category.product.show');
Route::get('/products/{slug}', [StorefrontController::class, 'productDetail'])->name('storefront.product.show');
Route::get('/shop', [StorefrontController::class, 'products'])->name('storefront.shop');
Route::get('/product-detail', [StorefrontController::class, 'productDetail'])->name('storefront.product.detail');
Route::get('/view-cart', [StorefrontController::class, 'cart'])->name('storefront.cart');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('storefront.checkout');
Route::get('/checkout/account', [CheckoutController::class, 'account'])->name('storefront.checkout.account');
Route::get('/checkout/address', [CheckoutController::class, 'address'])->name('storefront.checkout.address');
Route::get('/checkout/postal-code/{postalCode}', [CheckoutAddressController::class, 'postalCode'])->name('storefront.checkout.postal-code.show');
Route::post('/checkout/address/select', [CheckoutAddressController::class, 'select'])->name('storefront.checkout.addresses.select');
Route::post('/checkout/addresses', [CheckoutAddressController::class, 'store'])->name('storefront.checkout.addresses.store');
Route::patch('/checkout/addresses/{address}', [CheckoutAddressController::class, 'update'])->name('storefront.checkout.addresses.update');
Route::post('/checkout/billing/same', [CheckoutAddressController::class, 'billingSame'])->name('storefront.checkout.billing.same');
Route::post('/checkout/billing-address/select', [CheckoutAddressController::class, 'selectBilling'])->name('storefront.checkout.billing-addresses.select');
Route::post('/checkout/billing-addresses', [CheckoutAddressController::class, 'storeBilling'])->name('storefront.checkout.billing-addresses.store');
Route::post('/checkout/fulfillment', [CheckoutController::class, 'fulfillment'])->name('storefront.checkout.fulfillment');
Route::post('/checkout/place-order', [CheckoutController::class, 'placeOrder'])->name('storefront.checkout.place-order');
Route::get('/checkout/order/{order}', [CheckoutController::class, 'success'])->name('storefront.checkout.success');
Route::get('/category/{parentSlug}/{slug}', [StorefrontController::class, 'categoryWithParent'])->name('storefront.category.child.show');
Route::get('/category/{slug}', [StorefrontController::class, 'category'])->name('storefront.category.show');
Route::get('/category/{categoryPath}', [StorefrontController::class, 'categoryPath'])
    ->where('categoryPath', '.+')
    ->name('storefront.category.path.show');
Route::get('/store/{slug}', [StorefrontController::class, 'store'])->name('storefront.store.show');
Route::get('/store/{slug}/category/{categorySlug}', [StorefrontController::class, 'storeCategory'])->name('storefront.store.category.show');

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'authenticate'])->name('authenticate');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth', 'admin.role'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('master/shop-audiences', ShopAudienceController::class)
            ->except(['show'])
            ->names('master.shop-audiences');
        Route::resource('master/customer-cancellation-reasons', CustomerCancellationReasonController::class)
            ->except(['show'])
            ->parameters(['customer-cancellation-reasons' => 'customerCancellationReason'])
            ->names('master.customer-cancellation-reasons');
        Route::resource('master/brands', BrandController::class)
            ->except(['show'])
            ->names('master.brands');
        Route::post('master/tax-classes/{taxClass}/restore', [TaxClassController::class, 'restore'])
            ->withTrashed()
            ->name('master.tax-classes.restore');
        Route::post('master/tax-classes/{taxClass}/rates/{taxRate}/restore', [TaxRateController::class, 'restore'])
            ->withTrashed()
            ->name('master.tax-classes.rates.restore');
        Route::resource('master/tax-classes/{taxClass}/rates', TaxRateController::class)
            ->except(['index', 'show'])
            ->parameters(['rates' => 'taxRate'])
            ->names('master.tax-classes.rates');
        Route::post('master/tax-rates/{taxRate}/components/{component}/restore', [TaxRateComponentController::class, 'restore'])
            ->withTrashed()
            ->name('master.tax-rates.components.restore');
        Route::resource('master/tax-rates/{taxRate}/components', TaxRateComponentController::class)
            ->except(['index', 'show'])
            ->parameters(['components' => 'component'])
            ->names('master.tax-rates.components');
        Route::resource('master/tax-classes', TaxClassController::class)
            ->names('master.tax-classes');
        Route::post('master/order-statuses/{orderStatus}/restore', [OrderStatusController::class, 'restore'])
            ->withTrashed()
            ->name('master.order-statuses.restore');
        Route::resource('master/order-statuses', OrderStatusController::class)
            ->except(['show'])
            ->names('master.order-statuses');
        Route::post('master/payment-statuses/{paymentStatus}/restore', [PaymentStatusController::class, 'restore'])
            ->withTrashed()
            ->name('master.payment-statuses.restore');
        Route::resource('master/payment-statuses', PaymentStatusController::class)
            ->except(['show'])
            ->names('master.payment-statuses');
        Route::post('master/postal-codes/{postalCode}/restore', [PostalCodeController::class, 'restore'])
            ->withTrashed()
            ->name('master.postal-codes.restore');
        Route::resource('master/postal-codes', PostalCodeController::class)
            ->names('master.postal-codes');
        Route::post('master/postal-code-restrictions/{postalCodeRestriction}/restore', [PostalCodeRestrictionController::class, 'restore'])
            ->withTrashed()
            ->name('master.postal-code-restrictions.restore');
        Route::patch('master/postal-code-restrictions/{postalCodeRestriction}/toggle-status', [PostalCodeRestrictionController::class, 'toggleStatus'])
            ->name('master.postal-code-restrictions.toggle-status');
        Route::resource('master/postal-code-restrictions', PostalCodeRestrictionController::class)
            ->except(['show'])
            ->parameters(['postal-code-restrictions' => 'postalCodeRestriction'])
            ->names('master.postal-code-restrictions');
        Route::get('master/catalogue-requests', [CatalogueMasterRequestController::class, 'index'])
            ->name('master.catalogue-requests.index');
        Route::put('master/catalogue-requests/{catalogueMasterRequest}', [CatalogueMasterRequestController::class, 'update'])
            ->name('master.catalogue-requests.update');
        Route::get('master/product-categories/{productCategory}/attribute-groups', [ProductCategoryAttributeGroupController::class, 'edit'])
            ->name('master.product-categories.attribute-groups.edit');
        Route::put('master/product-categories/{productCategory}/attribute-groups', [ProductCategoryAttributeGroupController::class, 'update'])
            ->name('master.product-categories.attribute-groups.update');
        Route::post('master/product-categories/bulk-tax-class', [ProductCategoryController::class, 'bulkTaxClass'])
            ->name('master.product-categories.bulk-tax-class');
        Route::resource('master/product-categories', ProductCategoryController::class)
            ->parameters(['product-categories' => 'productCategory'])
            ->names('master.product-categories');
        Route::get('master/product-attribute-reference', [ProductAttributeGroupController::class, 'reference'])
            ->name('master.product-attribute-reference.index');
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
        Route::post('banners/{banner}/restore', [BannerController::class, 'restore'])
            ->withTrashed()
            ->name('banners.restore');
        Route::get('banner-library', [BannerLibraryController::class, 'index'])
            ->name('banner-library.index');
        Route::patch('banners/{banner}/replace-template', [BannerController::class, 'replaceTemplate'])
            ->name('banners.replace-template');
        Route::patch('banner-templates/{banner_template}/toggle-status', [BannerTemplateController::class, 'toggleStatus'])
            ->name('banner-templates.toggle-status');
        Route::resource('banner-templates', BannerTemplateController::class)
            ->except(['show', 'destroy']);
        Route::resource('banners', BannerController::class);
        Route::get('system-settings', [SystemSettingController::class, 'index'])
            ->name('system-settings.index');
        Route::get('system-settings/{systemSetting}/edit', [SystemSettingController::class, 'edit'])
            ->name('system-settings.edit');
        Route::put('system-settings/{systemSetting}', [SystemSettingController::class, 'update'])
            ->name('system-settings.update');
        Route::post('products/bulk-action', [ProductController::class, 'bulkAction'])
            ->name('products.bulk-action');
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])
            ->name('products.duplicate');
        Route::post('products/{product}/archive', [ProductController::class, 'archive'])
            ->name('products.archive');
        Route::post('products/{product}/restore-archive', [ProductController::class, 'restoreArchive'])
            ->name('products.restore-archive');
        Route::post('products/{product}/restore-trash', [ProductController::class, 'restoreTrash'])
            ->withTrashed()
            ->name('products.restore-trash');
        Route::delete('products/{product}/force', [ProductController::class, 'forceDestroy'])
            ->withTrashed()
            ->name('products.force-destroy');
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
        Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

});
