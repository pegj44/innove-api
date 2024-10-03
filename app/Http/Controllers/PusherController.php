<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Pusher\Pusher;

class PusherController extends Controller
{
    public function authenticateUnit(Request $request)
    {
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            ['cluster' => env('PUSHER_APP_CLUSTER'), 'useTLS' => true]
        );

        $auth = $pusher->authorizeChannel($request->input('channel_name'), $request->input('socket_id'));

        return response($auth);
    }

    public static function checkUnitConnection($acountId, $unitId)
    {
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            ['cluster' => env('PUSHER_APP_CLUSTER'), 'useTLS' => true]
        );

        $channelName = 'private-unit.'. $acountId .'.'. $unitId;

        try {
            $channelInfo = $pusher->getChannelInfo($channelName, []);

            return $channelInfo->occupied;

        } catch (Exception $e) {
            info(print_r([
                'checkConnectionError' => 'Error fetching channel info: ' . $e->getMessage()
            ], true));

            return false;
        }
    }
}
