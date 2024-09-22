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

Broadcast::channel('unit.{id}', function ($user, $id) {
//    return (int) $user->id === (int) $id;
    info(print_r([
        'broadCast2' => [
            'id' => $id
        ],
    ], true));
    return true;
});
