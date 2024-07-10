<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithBasicAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Check if the request contains email and password in headers
        $email = $request->header('X-Auth-Email');
        $password = $request->header('X-Auth-Password');

        if (!$email || !$password) {
            return response()->json(['message' => 'Unauthorized. Email and Password required'], 401);
        }

        // Attempt to authenticate the user
        $credentials = ['email' => $email, 'password' => $password];
        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Unauthorized. Invalid Email or Password'], 401);
        }

        return $next($request);
    }
}
