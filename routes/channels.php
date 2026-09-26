<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Здесь регистрируются авторизованные каналы трансляции событий.
| Laravel проверяет, может ли пользователь слушать конкретный канал.
|
*/

// Канал грузовика - только водитель этого грузовика (для LoadingCompleted)
Broadcast::channel('truck.{id}', function ($user, $id) {
    $truck = \App\Models\Truck::find($id);

    Log::debug('CHANNEL truck AUTH', [
        'user_id' => $user->id,
        'user_role' => $user->role,
        'truck_id_param' => $id,
        'truck_found' => $truck ? 'yes' : 'no',
        'truck_driver_id' => $truck?->driver_id,
    ]);

    // Водитель может слушать только свой грузовик
    return $truck && (int)$user->id === (int)$truck->driver_id;
});

// Канал водителя - только водитель конкретного грузовика
Broadcast::channel('driver.{truckId}', function ($user, $truckId) {
    $truck = \App\Models\Truck::find($truckId);

    Log::debug('CHANNEL driver AUTH', [
        'user_id' => $user->id,
        'user_role' => $user->role,
        'truck_id_param' => $truckId,
        'truck_found' => $truck ? 'yes' : 'no',
        'truck_driver_id' => $truck?->driver_id,
    ]);

    return $truck && (int)$user->id === (int)$truck->driver_id;
});

// Канал экскаватора/забоя - операторы закреплённые за экскаватором
Broadcast::channel('miner.{id}', function ($user, $id) {
    $userFresh = \App\Models\User::find($user->id);
    
    Log::debug('CHANNEL miner AUTH', [
        'user_id' => $user->id,
        'user_role' => $user->role,
        'user_position' => $user->position,
        'user_miner_id_old' => $user->miner_id,
        'user_miner_id_fresh' => $userFresh?->miner_id,
        'channel_miner_id' => $id,
        'is_admin' => $user->role === 'admin',
    ]);

    // Админ может слушать любой канал
    if ($user->role === 'admin') {
        return true;
    }

    // Проверяем position (английское значение: 'excavator_operator', 'master' и т.д.)
    // и совпадение miner_id
    $isAuthorizedRole = in_array($user->position, ['excavator_operator', 'master', 'admin']);
    $minerIdMatches = $userFresh && (int)$userFresh->miner_id === (int)$id;
    
    return $isAuthorizedRole && $minerIdMatches;
});

// Канал диспетчера - только диспетчеры и админы
Broadcast::channel('dispatcher', function ($user) {
    Log::debug('CHANNEL dispatcher AUTH', [
        'user_id' => $user->id,
        'user_role' => $user->role,
        'user_position' => $user->position,
    ]);

    return in_array($user->position, ['dispatcher', 'admin']) || $user->role === 'admin';
});
