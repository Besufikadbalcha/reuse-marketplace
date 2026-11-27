<?php

use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminProductImageController;
use App\Http\Controllers\AdminPromotionController;
use App\Http\Controllers\AdminPromotionProofController;
use App\Http\Controllers\AdminSellerController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductSuggestionController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerProductImageController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\SellerPromotionController;
use App\Http\Controllers\SellerPromotionProofController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductController::class, 'index'])->name('home');

Route::get('/search/suggestions', ProductSuggestionController::class)
    ->middleware('throttle:60,1')
    ->name('search.suggestions');

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::put('/theme', [ThemeController::class, 'update'])->name('theme.update');

Route::get('/categories/{category:slug}', [ProductController::class, 'category'])
    ->where('category', '[a-z0-9-]+')
    ->name('categories.show');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:registration')->name('register.store');

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:password-update')->name('password.update');
});

Route::view('/how-it-works', 'how-it-works')->name('how-it-works');
Route::view('/about-us', 'about-us')->name('about-us');

Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('privacy-policy');
Route::get('/terms-and-conditions', [LegalController::class, 'terms'])->name('terms-and-conditions');

Route::get('/products/{product}/images/{image}', [ProductImageController::class, 'show'])
    ->scopeBindings()
    ->name('product-images.show');

Route::get('/sellers/{seller}', [SellerProfileController::class, 'show'])
    ->name('sellers.show');

Route::get('/products/{slug}', [ProductController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('products.show');

Route::middleware('auth')->group(function () {
    Route::redirect('/my-account', '/orders')->name('my-account');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/notifications', NotificationController::class)->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/products/{product:slug}/checkout', [OrderController::class, 'create'])->name('checkout.create');
    Route::post('/products/{product:slug}/checkout', [OrderController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('throttle:order-cancellation')->name('orders.cancel');
    Route::post('/orders/{order}/items/{orderItem}/confirm-received', [OrderController::class, 'confirmReceived'])->name('orders.items.confirm-received');
    Route::post('/orders/{order}/items/{orderItem}/report-problem', [OrderController::class, 'reportProblem'])->middleware('throttle:order-cancellation')->name('orders.items.report-problem');

    Route::get('/seller/orders', [SellerOrderController::class, 'index'])->name('seller.orders.index');
    Route::get('/seller/orders/{order}', [SellerOrderController::class, 'show'])->name('seller.orders.show');
    Route::post('/seller/orders/{order}/items/{orderItem}/confirm', [SellerOrderController::class, 'confirm'])->name('seller.orders.confirm');
    Route::post('/seller/orders/{order}/items/{orderItem}/ready-for-pickup', [SellerOrderController::class, 'readyForPickup'])->name('seller.orders.ready-for-pickup');
    Route::post('/seller/orders/{order}/items/{orderItem}/out-for-delivery', [SellerOrderController::class, 'outForDelivery'])->name('seller.orders.out-for-delivery');
    Route::post('/seller/orders/{order}/items/{orderItem}/cash-received', [SellerOrderController::class, 'recordCashReceived'])->name('seller.orders.cash-received');
    Route::post('/seller/orders/{order}/items/{orderItem}/complete', [SellerOrderController::class, 'complete'])->name('seller.orders.complete');

    Route::get('/seller/products', [SellerProductController::class, 'index'])->name('seller.products.index');
    Route::get('/seller/products/create', [SellerProductController::class, 'create'])->name('seller.products.create');
    Route::post('/seller/products', [SellerProductController::class, 'store'])->name('seller.products.store');
    Route::get('/seller/products/{product}', [SellerProductController::class, 'show'])->name('seller.products.show');
    Route::get('/seller/products/{product}/edit', [SellerProductController::class, 'edit'])->name('seller.products.edit');
    Route::put('/seller/products/{product}', [SellerProductController::class, 'update'])->name('seller.products.update');
    Route::delete('/seller/products/{product}', [SellerProductController::class, 'destroy'])->name('seller.products.destroy');
    Route::get('/seller/products/{product}/images/{image}', [SellerProductImageController::class, 'show'])
        ->scopeBindings()
        ->name('seller.products.images.show');

    Route::get('/seller/products/{product}/promotions/create', [SellerPromotionController::class, 'create'])->name('seller.promotions.create');
    Route::post('/seller/products/{product}/promotions', [SellerPromotionController::class, 'store'])->name('seller.promotions.store');
    Route::get('/seller/promotions', [SellerPromotionController::class, 'index'])->name('seller.promotions.index');
    Route::get('/seller/promotions/{promotion}', [SellerPromotionController::class, 'show'])->name('seller.promotions.show');
    Route::get('/seller/promotions/{promotion}/resubmit', [SellerPromotionController::class, 'resubmit'])->name('seller.promotions.resubmit');
    Route::post('/seller/promotions/{promotion}/resubmit', [SellerPromotionController::class, 'storeResubmission'])->name('seller.promotions.resubmit.store');
    Route::get('/seller/promotions/{promotion}/payment-submissions/{paymentSubmission}/proof', [SellerPromotionProofController::class, 'show'])
        ->scopeBindings()
        ->name('seller.promotions.proof');
});

Route::middleware(['auth', 'can:moderateProducts'])->prefix('admin')->as('admin.')->group(function () {
    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::get('/sellers/{seller}', [AdminSellerController::class, 'show'])->name('sellers.show');
    Route::post('/sellers/{seller}/archive', [AdminSellerController::class, 'archive'])->name('sellers.archive');
    Route::post('/sellers/{seller}/restore', [AdminSellerController::class, 'restore'])->name('sellers.restore');
    Route::post('/sellers/{seller}/listings/restore', [AdminSellerController::class, 'restoreListings'])->name('sellers.listings.restore');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('products.show');
    Route::post('/products/{product}/approve', [AdminProductController::class, 'approve'])->name('products.approve');
    Route::post('/products/{product}/reject', [AdminProductController::class, 'reject'])->name('products.reject');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/restore', [AdminProductController::class, 'restore'])->name('products.restore');
    Route::get('/products/{product}/images/{image}', [AdminProductImageController::class, 'show'])
        ->scopeBindings()
        ->name('products.images.show');

    Route::get('/promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/{promotion}', [AdminPromotionController::class, 'show'])->name('promotions.show');
    Route::get('/promotions/{promotion}/payment-submissions/{paymentSubmission}/proof', [AdminPromotionProofController::class, 'show'])
        ->scopeBindings()
        ->name('promotions.proof');
    Route::post('/promotions/{promotion}/payment-submissions/{paymentSubmission}/approve', [AdminPromotionController::class, 'approve'])
        ->scopeBindings()
        ->name('promotions.payments.approve');
    Route::post('/promotions/{promotion}/payment-submissions/{paymentSubmission}/reject', [AdminPromotionController::class, 'reject'])
        ->scopeBindings()
        ->name('promotions.payments.reject');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
});
