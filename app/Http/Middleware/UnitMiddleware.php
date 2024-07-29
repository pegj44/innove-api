<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UnitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

//        $user = User::with('unitUserLogin.user.units')
//            ->where('id', auth()->id())
//            ->first()->toArray();
//
//        info(print_r([
//            'authUnit' => $request->all(),
//            'auth' => auth()->id()
//        ], true));
//
//        if (empty($user) || !isset($user['unit_user_login']['user']['units'])) {
//            return response()->json(['message' => 'This unit is not registered.'], 401);
//        }
//
//        $hasUnit = false;
//
//        foreach ($user['unit_user_login']['user']['units'] as $unit) {
//            if ($unit['ip_address'] === $request->get('ip')) {
//                $hasUnit = true;
//                break;
//            }
//        }
//
//        if (!$hasUnit) {
//            return response()->json(['message' => 'This unit is not registered.'], 401);
//        }

        return $next($request);
    }
}
