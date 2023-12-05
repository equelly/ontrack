<?php

use App\Models\Order;
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

Route::get('/', function () {
    return view('welcome');
});
Route::group(['namespace'=>'App\Http\Controllers\Order'], function (){
    Route::get('/orders', 'IndexController')->name('order.index');
    Route::get('/orders/create', 'CreateController')->name('order.create');
    Route::post('/orders', 'StoreController')->name('order.store');//для указания обработчика запроса в форме
    Route::get('/orders/{order}', 'ShowController')->name('order.show');
    Route::get('/orders/{order}/edit', 'EditController')->name('order.edit');
    Route::patch('/orders/{order}', 'UpdateController')->name('order.update');
    Route::delete('/orders/{order}', 'DestroyController')->name('order.destroy');
});
