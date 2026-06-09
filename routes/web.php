<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChairController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Customer\CartController as CustomerCartController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\PagesController as CustomerPagesController;
use App\Http\Controllers\Customer\ProductController as CustomerProductController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\InventController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Pagescontroller;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ShowcaseController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoreConfigController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// AUTH CONTROLLER
Route::get('/', [AuthController::class, 'login'])->name('login');
Route::match(['get', 'post'], '/signin', [AuthController::class, 'signin'])->name('signin');

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});

Route::middleware(['auth:web,staff', 'ensure'])->group(function () {
    // ADMIN

    // PAGES CONTROLLER
    Route::get('/dashboard', [Pagescontroller::class, 'dashboard'])->name('dashboard');
    Route::get('/search', [Pagescontroller::class, 'search'])->name('search');
    Route::get('/profile', [Pagescontroller::class, 'profile'])->name('profile');

    // STORE CONTROLLER
    Route::get('/addstore', [StoreController::class, 'create'])->name('addstore');
    Route::post('/poststore', [StoreController::class, 'store'])->name('poststore');
    Route::put('/store/{id}/update', [StoreController::class, 'update'])->name('updatestore');

    // ACTIVITY LOG
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activityLog');

    // STORE CONFIG
    Route::get('/storeconfig', [StoreConfigController::class, 'index'])->name('storeConfig');
    Route::put('/storeconfig/update', [StoreConfigController::class, 'update'])->name('updatestoreConfig');

    // CHAIR CONTROLLER
    Route::get('/chair', [ChairController::class, 'index'])->name('chair');
    Route::post('/postchair', [ChairController::class, 'store'])->name('postchair');
    Route::get('/chair/{id}/qr', [ChairController::class, 'qr'])->name('chairqr');
    Route::delete('/chair/{id}/delete', [ChairController::class, 'destroy'])->name('delchair');

    // QR CONTROLLER
    Route::get('/login/qr/{id}', [QrController::class, 'LoginQr'])->name('login-qr');

    // INVENT CONTROLLER (Master Bahan)
    Route::get('/invent', [InventController::class, 'index'])->name('invent');
    Route::post('/postinvent', [InventController::class, 'store'])->name('postinvent');
    Route::put('/invent/{id}/update', [InventController::class, 'update'])->name('updateinvent');
    Route::delete('/invent/{id}/delete', [InventController::class, 'destroy'])->name('delinvent');

    // STOCK CONTROLLER (Stok Bahan)
    Route::get('/stock', [StockController::class, 'index'])->name('stock');
    Route::post('/stock/receive', [StockController::class, 'receive'])->name('receiveinvent');
    Route::get('/stock/opname', [StockController::class, 'opnameForm'])->name('opname');
    Route::post('/stock/opname', [StockController::class, 'opname'])->name('opnameinvent');
    Route::get('/stock/opname-history', [StockController::class, 'opnameHistory'])->name('opnameHistory');


    // ORDER CONTROLLER
    Route::get('/order', [OrderController::class, 'index'])->name('order');
    Route::get('/order/create', [OrderController::class, 'create'])->name('addorder');
    Route::post('/order/checkout', [OrderController::class, 'checkout'])->name('checkout');
    Route::post('/order/open-bill', [OrderController::class, 'openBill'])->name('open-bill');
    Route::delete('/order/open-bill/{cartId}/cancel', [OrderController::class, 'cancelOpenBill'])->name('cancel-open-bill');
    Route::post('/order/midtrans-confirm/{orderId}', [OrderController::class, 'midtransConfirm'])->name('midtrans-confirm');
    Route::get('/order/{id}/resume-online', [OrderController::class, 'resumeOnline'])->name('order-resume-online');
    Route::get('/order/{id}/receipt', [OrderController::class, 'receipt'])->name('order-receipt');
    Route::get('/order/{id}/checker', [OrderController::class, 'checker'])->name('order-checker');
    Route::delete('/order/{id}/delete', [OrderController::class, 'destroy'])->name('delorder');
    Route::post('/order/{orderId}/archive', [OrderController::class, 'archive'])->name('archive');

    // CART CONTROLLER (admin)
    Route::post('/postcart', [CartController::class, 'store'])->name('postcart');
    Route::delete('/cart/{id}/delete', [CartController::class, 'destroy'])->name('removecart');

    // MENU CONTROLLER
    Route::get('/product', [ProductController::class, 'index'])->name('product');
    Route::post('/postproduct', [ProductController::class, 'store'])->name('postproduct');
    Route::get('/product/{id}/show', [ProductController::class, 'show'])->name('showproduct');
    Route::put('/product/{id}/update', [ProductController::class, 'update'])->name('updateproduct');
    Route::delete('/product/{id}/delete', [ProductController::class, 'destroy'])->name('delproduct');

    // INGREDIENT CONTROLLER
    Route::get('/ingridient', [IngredientController::class, 'index'])->name('ingridient');
    Route::put('/ingridient/{id}/upsert', [IngredientController::class, 'upsert'])->name('upsertingridient');
    Route::delete('/ingridient/{id}/delete', [IngredientController::class, 'destroy'])->name('delingridient');

    // STATION CONTROLLER (managed inside Store Config page)
    Route::post('/poststation', [StationController::class, 'store'])->name('poststation');
    Route::put('/station/{id}/update', [StationController::class, 'update'])->name('updatestation');
    Route::delete('/station/{id}/delete', [StationController::class, 'destroy'])->name('delstation');

    // CATEGORY CONTROLLER
    Route::get('/category', [CategoryController::class, 'index'])->name('category');
    Route::post('/postcategory', [CategoryController::class, 'store'])->name('postcategory');
    Route::put('/category/{id}/update', [CategoryController::class, 'update'])->name('updatecategory');
    Route::delete('/category/{id}/delete', [CategoryController::class, 'destroy'])->name('delcategory');

    // SHOWCASE CONTROLLER
    Route::get('/showcase', [ShowcaseController::class, 'index'])->name('showcase');
    Route::post('/postshowcase', [ShowcaseController::class, 'store'])->name('postshowcase');
    Route::put('/showcase/{id}/update', [ShowcaseController::class, 'update'])->name('updateshowcase');
    Route::delete('/showcase/{id}/delete', [ShowcaseController::class, 'destroy'])->name('delshowcase');

    // HISTORY CONTROLLER
    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/export-orders', [HistoryController::class, 'exportOrders'])->name('exportOrders');


    // DISCOUNT CONTROLLER
    Route::get('/discount', [DiscountController::class, 'index'])->name('discount');
    Route::post('/postdiscount', [DiscountController::class, 'store'])->name('postdiscount');
    Route::put('/discount/{id}/update', [DiscountController::class, 'update'])->name('updatediscount');
    Route::delete('/discount/{id}/delete', [DiscountController::class, 'destroy'])->name('deldiscount');

    // SETTLEMENT CONTROLLER
    Route::get('/settlement', [SettlementController::class, 'index'])->name('settlement');
    Route::get('/settlement/{id}/show', [SettlementController::class, 'show'])->name('showsettlement');
    Route::delete('/settlement/{id}/delete', [SettlementController::class, 'destroy'])->name('delsettlement');
    Route::get('/addstartamount', [SettlementController::class, 'startamount'])->name('addstartamount');
    Route::get('/addtotalamount', [SettlementController::class, 'totalamount'])->name('addtotalamount');
    Route::post('/createstart', [SettlementController::class, 'poststart'])->name('poststart');
    Route::post('/createtotal', [SettlementController::class, 'posttotal'])->name('posttotal');

    // CONSULT
    Route::get('/chats', [ChatController::class, 'chats'])->name('chats');
    Route::post('/gen', [ChatController::class, 'gen'])->name('gen');
});

// CUSTOMER routes (chair guard)
Route::middleware(['auth:chair', 'ensure'])->group(function () {
    // CUSTOMER PAGES CONTROLLER
    Route::get('/customer', [CustomerPagesController::class, 'home'])->name('user-home');
    Route::get('/customer/antrian', [CustomerPagesController::class, 'antrian'])->name('user-antrian');
    Route::get('/customer/akun', [CustomerPagesController::class, 'akun'])->name('user-akun');

    // CUSTOMER PRODUCT CONTROLLER
    Route::get('/customer/product', [CustomerProductController::class, 'product'])->name('user-product');
    Route::get('/customer/product/{id}', [CustomerProductController::class, 'show'])->name('user-show');

    // CUSTOMER CART CONTROLLER
    Route::get('/customer/cart', [CustomerCartController::class, 'cart'])->name('user-cart');
    Route::post('/customer/cart', [CustomerCartController::class, 'postcart'])->name('user-postcart');
    Route::delete('/customer/cart/{id}/delete', [CustomerCartController::class, 'removecart'])->name('user-removecart');
    Route::post('/customer/cart/acknowledge', [CustomerCartController::class, 'acknowledge'])->name('user-cart-acknowledge');
    Route::post('/customer/cart/reset', [CustomerCartController::class, 'reset'])->name('user-cart-reset');

    // CUSTOMER ORDER CONTROLLER
    Route::post('/customer/order', [CustomerOrderController::class, 'postorder'])->name('user-postorder');
    Route::get('/customer/payment', [CustomerOrderController::class, 'payment'])->name('user-payment');
});

// LOGOUT - works for admin (web), staff, and chair
Route::middleware(['auth:web,staff,chair'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});