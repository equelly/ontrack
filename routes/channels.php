<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('driver.{truckId}', function ($user, $truckId) {
    logger()->debug('CHANNEL driver', [
        'user_id' => $user->id ?? null,
        'role' => $user->role ?? null,
        'truckId' => $truckId,
    ]);

    // 🔴 КРИТИЧНО: вернуть НЕ false
    return $user->role === 'driver';
});

// Broadcast::channel('dispatcher', function ($user) {
//     logger()->debug('CHANNEL dispatcher', [
//         'user_id' => $user->id ?? null,
//         'role' => $user->role ?? null,
//     ]);

//     return $user->role === 'dispatcher';
// });
