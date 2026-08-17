<?php

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\KycController;
use App\Http\Controllers\Frontend\ProductCatalogController;
use App\Http\Controllers\Frontend\ProfileController;
use App\Http\Controllers\Frontend\StoreController;
use App\Http\Controllers\Frontend\UserDashboardController;
use App\Http\Controllers\Frontend\VendorDashboardController;
use App\Http\Controllers\Frontend\VendorDigitalProductFileController;
use App\Http\Controllers\Frontend\VendorProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [ProductCatalogController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductCatalogController::class, 'show'])
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('products.show');

Route::group(['middleware' => ['auth', 'verified']], function () {

    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');

    /** Profile Routes */
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'profileUpdate'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'passwordUpdate'])->name('password.update');

    /** KYC Routes */
    Route::get('/kyc-verification', [KycController::class, 'index'])->name('kyc.index');
    Route::post('/kyc-verification', [KycController::class, 'store'])->name('kyc.store');
});

/** Vendor Routes */
Route::group(['prefix' => 'vendor', 'as' => 'vendor.', 'middleware' => ['auth', 'verified', 'user_role:vendor']], function () {

    Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('dashboard');

    /** Store Profile Routes */
    Route::get('/store-profile', [StoreController::class, 'index'])->name('store-profile.index');
    Route::put('/store-profile', [StoreController::class, 'update'])->name('store-profile.update');

    /** Product Routes */
    Route::get('/products', [VendorProductController::class, 'index'])->name('products.index');
    Route::get('/products/{type}/create', [VendorProductController::class, 'create'])->name('products.create');
    Route::post('/products/{type}/create', [VendorProductController::class, 'store'])
        ->whereIn('type', ['physical', 'digital'])
        ->name('products.store');
    Route::get('/products/physical/{product}/edit', [VendorProductController::class, 'edit'])->name('products.edit');
    Route::post('/products/physical/{product}/update', [VendorProductController::class, 'update'])->name('products.update');
    Route::post('/products/images/upload/{product}', [VendorProductController::class, 'uploadImages'])->name('products.images.upload');
    Route::delete('/products/images/{image}', [VendorProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::post('/products/images/reorder', [VendorProductController::class, 'imagesReorder'])->name('products.images.reorder');

    /** Product Attributes route */
    Route::post('/products/attributes/{product}/store', [VendorProductController::class, 'storeAttributes'])->name('products.attributes.store');
    Route::delete('/products/{product}/attributes/{attribute}', [VendorProductController::class, 'destroyAttribute'])
        ->name('products.attributes.destroy');

    /** Product variant routes */
    Route::post('/products/variants/{product}/update', [VendorProductController::class, 'updateVariants'])->name('products.variants.update');

    /** Digital product routes */
    Route::get('/products/digital/{product}/edit', [VendorProductController::class, 'editDigital'])->name('digital-products.edit');
    Route::post('/products/digital/file-upload', [VendorDigitalProductFileController::class, 'store'])
        ->middleware('throttle:300,1')
        ->name('digital-products.file.upload');
    Route::delete('/products/digital/{product}/{file}', [VendorDigitalProductFileController::class, 'destroy'])
        ->name('digital-products.file.destroy');
    Route::delete('/products/{product}', [VendorProductController::class, 'destroy'])->name('products.destroy');
});

require __DIR__ . '/auth.php';
