<?php


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Dump\DistributionController;
use App\Livewire\DriverPanel;
use App\Livewire\TestComponent;
use App\Events\TestBroadcast;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverRouteController;
use App\Http\Controllers\DispatcherController;
use App\Http\Controllers\ExcavatorController;
use App\Livewire\ExcavatorPanel;
use Illuminate\Support\Facades\Log;


Route::middleware(['auth', 'role:driver'])->group(function () {
    Route::post('/driver/route/ack', [DriverRouteController::class, 'ack']);
    Route::post('/driver/status', [DriverController::class, 'updateStatus']);
    Route::post('/driver/assign', [DriverController::class, 'assignForTruck']);
    
    // API endpoints
    Route::get('/driver/available-zones', [DriverController::class, 'availableZones']);
    Route::post('/driver/reassign-zone', [DriverController::class, 'reassignZone']);
    
    // Livewire панель водителя
    Route::get('/driver/{truck}', \App\Livewire\DriverPanel::class)->name('driver.panel');
});


Route::resource('drivers', DriverController::class)->parameters(['drivers' => 'truck']);
/*
|--------------------------------------------------------------------------
| Панель машиниста экскаватора (Livewire)
|--------------------------------------------------------------------------
*/
Route::get('/excavator', function () {
    return view('excavator.index');
})->name('excavator.index')->middleware(['auth', 'role:excavator_operator,admin']);

// Старые API маршруты (оставляем для совместимости, если нужны)
Route::prefix('excavator')->name('excavator.')->middleware(['auth', 'role:excavator_operator,admin'])->group(function () {
    // API для внешних запросов, если понадобятся
    // Route::post('/set-miner', [ExcavatorController::class, 'setMiner'])->name('set-miner');
    // Route::post('/set-rock', [ExcavatorController::class, 'setRock'])->name('set-rock');
    // Route::post('/truck/{truck}/confirm', [ExcavatorController::class, 'confirmArrival'])->name('confirm-arrival');
    // Route::post('/truck/{truck}/complete', [ExcavatorController::class, 'completeLoading'])->name('complete-loading');
});




//Route::get('/dispatcher', DispatcherPanel::class)->name('dispatcher');
Route::get('/test', TestComponent::class)->name('test'); 
Route::get('/driver/{truckId}', DriverPanel::class)->name('driver.panel');


// базовый в приложении не применяю
Route::get('/dump/distribution', [DistributionController::class, 'index']);


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
         Route::put('/dump/{dump}', 'UpdateController')->name('dump.update');
         Route::delete('/dump/{dump}', 'DestroyController')->name('dump.delete');
    });

    Route::group(['namespace' => 'Miner'], function () {
        Route::resource('miners', 'MinersController');   // Создает: miners.index, miners.create, miners.store и т.д.

        });    
        // РОУТЫ ДЛЯ РАСПРЕДЕЛЕНИЯ
    Route::get('/distribution-status', [DistributionController::class, 'status']);
    Route::get('/distribute', [DistributionController::class, 'distribute']);
    Route::post('/distribute', [DistributionController::class, 'distribute']);  // ← Для AJAX
    Route::get('/test-optimal-zone/{minerId}', [DistributionController::class, 'testOptimalZone']);
    Route::get('/distribution', [DistributionController::class, 'index'])->name('distribution.index');
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

// =========================================
// МАРШРУТЫ ДИСПЕТЧЕРА
// =========================================
Route::middleware(['auth', 'role:dispatcher'])->group(function () {
    Route::get('/dispatcher', [App\Http\Controllers\DispatcherController::class, 'index'])->name('dispatcher.index');
    
    // Управление грузовиками
    Route::post('/dispatcher/truck/{truck}/reassign', [App\Http\Controllers\DispatcherController::class, 'reassign']);
    Route::post('/dispatcher/truck/{truck}/breakdown', [App\Http\Controllers\DispatcherController::class, 'breakdown']);
    Route::post('/dispatcher/truck/{truck}/maintenance', [App\Http\Controllers\DispatcherController::class, 'maintenance']);
    Route::post('/dispatcher/truck/{truck}/fueling', [App\Http\Controllers\DispatcherController::class, 'fueling']);
    Route::post('/dispatcher/truck/{truck}/free', [App\Http\Controllers\DispatcherController::class, 'setFree']);
    Route::get('/dispatcher/routes', [App\Http\Controllers\DispatcherController::class, 'availableRoutes']);
});
