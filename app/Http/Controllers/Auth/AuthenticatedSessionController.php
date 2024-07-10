<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticatedSessionController extends Controller
{
    public function authenticateUserToken(Request $request)
    {
        return response()->json([
            'authenticated' => true
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {

            $tokenName = env('ADMIN_TOKEN_NAME');
            $user = Auth::user();
            $this->revokeToken($user->id, $tokenName);

            $token = $user->createToken($tokenName, ['admin'])->plainTextToken;

            return response()->json([
                'token' => $token,
                'userId' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        return response()->json([
            'errors' => 'Invalid credentials'
        ], 401);
    }

    private function revokeToken($userId, $tokenName)
    {
        $user = User::find($userId);

        if ($user) {
            $token = $user->tokens()->where('name', $tokenName)->first();

            if ($token) {
                $token->delete();
                return response()->json(['message' => 'Token revoked successfully.'], 200);
            } else {
                return response()->json(['message' => 'Token not found.'], 404);
            }
        } else {
            return response()->json(['message' => 'User not found.'], 404);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
