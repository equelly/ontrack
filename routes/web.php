<?php

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/main', function () {
    return view('main');
});

Route::group(['namespace'=>'App\Http\Controllers\User', 'prefix'=>'user', 'middleware'=>'user'], function (){
    Route::group(['namespace'=>'Order'], function(){
        Route::get('/orders', 'IndexController')->name('order.index');
        Route::get('/orders/create', 'CreateController')->name('order.create');
        Route::post('/orders', 'StoreController')->name('order.store');//для указания обработчика запроса в форме
        Route::get('/orders/{order}', 'ShowController')->name('order.show');
        Route::get('/orders/{order}/edit', 'EditController')->name('order.edit');
        Route::patch('/orders/{order}', 'UpdateController')->name('order.update');
        Route::delete('/orders/{order}', 'DestroyController')->name('order.destroy');
        Route::get('/search', 'SearchController')->name('order.search');
    });
    Route::group(['namespace'=>'Dump'], function(){
        Route::get('/dumps', 'IndexController')->name('dump.index');
         Route::get('/dumps/create', 'CreateController')->name('dump.create');
         Route::post('/dump', 'StoreController')->name('dump.store');
         Route::get('/dump/{dump}', 'ShowController')->name('dump.show');
         Route::get('/dump/{dump}/edit', 'EditController')->name('dump.edit');
         Route::patch('/dump/{dump}', 'UpdateController')->name('dump.update');
         Route::delete('/dump/{dump}', 'DestroyController')->name('dump.delete');
    });
  
});
Route::group(['namespace'=>'App\Http\Controllers\Admin', 'prefix'=>'admin', 'middleware'=>'admin'], function (){
    Route::group(['namespace'=>'Order'], function(){
         Route::get('/order', 'IndexController')->name('admin.order.index');
         Route::get('/order/create', 'CreateController')->name('admin.order.create');
         Route::post('/order', 'StoreController')->name('admin.order.store');
         Route::get('/order/{order}', 'ShowController')->name('admin.order.show');
         Route::get('/order/category/{category}', 'ShowByCategoryController')->name('admin.order.showByCategory');
         Route::get('/order/{order}/edit', 'EditController')->name('admin.order.edit');
         Route::patch('/order/{order}', 'UpdateController')->name('admin.order.update');
         Route::delete('/order/{order}', 'DestroyController')->name('admin.order.delete');
    });
    Route::group(['namespace'=>'Mashine'], function(){
        Route::get('/mashine', 'IndexController')->name('admin.mashine.index');
        Route::get('/mashine/create', 'CreateController')->name('admin.mashine.create');
        Route::post('/mashine', 'StoreController')->name('admin.mashine.store');
        Route::delete('/mashine/{mashine}', 'DestroyController')->name('admin.mashine.delete');
    });
    Route::group(['namespace'=>'Set'], function(){
        Route::get('/sets', 'IndexController')->name('admin.set.index');
        Route::get('/sets/create', 'CreateController')->name('admin.set.create');
        Route::post('/sets', 'StoreController')->name('admin.set.store');
        Route::delete('/sets/{set}', 'DestroyController')->name('admin.set.delete');
    });
    Route::group(['namespace'=>'User'], function(){
        Route::get('/users', 'IndexController')->name('admin.users.index');
        Route::delete('/users/{user}', 'DestroyController')->name('admin.users.delete');
    });
});
Auth::routes();

Route::get('/', [App\Http\Controllers\IndexController::class, 'index'])->name('welcome');
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
