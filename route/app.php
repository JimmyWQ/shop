<?php

use app\middleware\AdminAuth;
use app\middleware\Auth;
use think\facade\Route;

// 商城前台
Route::get('/', 'Index/index')->name('home');
Route::get('shop', 'Product/index')->name('shop');
Route::get('category/:slug', 'Product/category')->name('category.show')->pattern(['slug' => '[a-z0-9-]+']);
Route::get('product/:slug', 'Product/detail')->name('product.show')->pattern(['slug' => '[a-z0-9-]+']);

// 品牌与服务页面
Route::get('about', 'Page/about')->name('about');
Route::get('service', 'Page/service')->name('service');
Route::get('contact', 'Page/contact')->name('contact');

// 购物车
Route::get('cart', 'Cart/index')->name('cart.index');
Route::post('cart/add', 'Cart/add')->name('cart.add');
Route::post('cart/update', 'Cart/update')->name('cart.update');
Route::post('cart/remove', 'Cart/remove')->name('cart.remove');
Route::post('cart/clear', 'Cart/clear')->name('cart.clear');

// 登录注册
Route::get('login', 'Auth/login')->name('login');
Route::post('login', 'Auth/doLogin');
Route::get('register', 'Auth/register')->name('register');
Route::post('register', 'Auth/doRegister');
Route::post('logout', 'Auth/logout')->name('logout');

// 结算与个人中心
Route::get('checkout', 'Checkout/index')->name('checkout.index')->middleware(Auth::class);
Route::post('checkout', 'Checkout/submit')->name('checkout.submit')->middleware(Auth::class);

Route::get('account', 'Account/index')->name('account.index')->middleware(Auth::class);
Route::get('account/orders', 'Account/orders')->name('account.orders')->middleware(Auth::class);
Route::post('account/profile', 'Account/updateProfile')->name('account.profile')->middleware(Auth::class);
Route::post('account/password', 'Account/updatePassword')->name('account.password')->middleware(Auth::class);

Route::get('order/:order_no', 'Order/show')->name('order.show')->pattern(['order_no' => '[A-Za-z0-9]+'])->middleware(Auth::class);
Route::post('order/:order_no/pay', 'Order/pay')->name('order.pay')->pattern(['order_no' => '[A-Za-z0-9]+'])->middleware(Auth::class);
Route::post('order/:order_no/cancel', 'Order/cancel')->name('order.cancel')->pattern(['order_no' => '[A-Za-z0-9]+'])->middleware(Auth::class);
Route::post('order/:order_no/complete', 'Order/complete')->name('order.complete')->pattern(['order_no' => '[A-Za-z0-9]+'])->middleware(Auth::class);

// 管理后台登录
Route::get('admin/login', 'admin/Auth/login')->name('admin.login');
Route::post('admin/login', 'admin/Auth/doLogin');

// 管理后台（需要管理员身份）
Route::get('admin', 'admin/Dashboard/index')->name('admin.dashboard')->completeMatch()->middleware(AdminAuth::class);

Route::group('admin', function () {
    Route::post('logout', 'admin/Auth/logout')->name('admin.logout');

    Route::get('products', 'admin/Product/index')->name('admin.products');
    Route::get('products/create', 'admin/Product/create')->name('admin.products.create');
    Route::post('products', 'admin/Product/store')->name('admin.products.store');
    Route::get('products/:id/edit', 'admin/Product/edit')->name('admin.products.edit')->pattern(['id' => '\d+']);
    Route::post('products/:id', 'admin/Product/update')->name('admin.products.update')->pattern(['id' => '\d+']);
    Route::post('products/:id/delete', 'admin/Product/delete')->name('admin.products.delete')->pattern(['id' => '\d+']);
    Route::post('products/:id/toggle', 'admin/Product/toggle')->name('admin.products.toggle')->pattern(['id' => '\d+']);

    Route::get('categories', 'admin/Category/index')->name('admin.categories');
    Route::get('categories/create', 'admin/Category/create')->name('admin.categories.create');
    Route::post('categories', 'admin/Category/store')->name('admin.categories.store');
    Route::get('categories/:id/edit', 'admin/Category/edit')->name('admin.categories.edit')->pattern(['id' => '\d+']);
    Route::post('categories/:id', 'admin/Category/update')->name('admin.categories.update')->pattern(['id' => '\d+']);
    Route::post('categories/:id/delete', 'admin/Category/delete')->name('admin.categories.delete')->pattern(['id' => '\d+']);
    Route::post('categories/:id/toggle', 'admin/Category/toggle')->name('admin.categories.toggle')->pattern(['id' => '\d+']);

    Route::get('orders', 'admin/Order/index')->name('admin.orders');
    Route::get('orders/:order_no', 'admin/Order/detail')->name('admin.orders.detail')->pattern(['order_no' => '[A-Za-z0-9]+']);
    Route::post('orders/:order_no/action', 'admin/Order/action')->name('admin.orders.action')->pattern(['order_no' => '[A-Za-z0-9]+']);

    Route::get('users', 'admin/User/index')->name('admin.users');
    Route::post('users/:id/toggle', 'admin/User/toggle')->name('admin.users.toggle')->pattern(['id' => '\d+']);
})->middleware(AdminAuth::class);
