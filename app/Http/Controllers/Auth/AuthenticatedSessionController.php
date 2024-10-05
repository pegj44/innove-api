<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\MachineJobs;
use App\Models\TradingUnitsModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticatedSessionController extends Controller
{
    public function createToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {

            $tokenName = env('ADMIN_TOKEN_NAME');
            $user = Auth::user();

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

    public function authenticateUserToken(Request $request)
    {
        return response()->json([
            'authenticated' => true
        ]);
    }

    public function unitUiLogin(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->route('main');
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

    public function loginUnit(Request $request)
    {
        $validator = Validator::make($request->only(['username', 'password']), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => __('Login validation failed.')
            ], 401);
        }

        $loginData = $request->only('username', 'password');
        $loginData['email'] = $request->get('username') .'@innovetechsolutions.rpahandler';

        unset($loginData['username']);

        if (Auth::attempt($loginData)) {

            $tokenName = env('UNIT_TOKEN_NAME');
            $userUnits = auth()->user()->account->units()->get();

            if (!$userUnits->isNotEmpty()) {
                return response()->json([
                    'errors' => 'No units registered to this account.'
                ], 401);
            }

            $hasUnit = false;

            foreach ($userUnits->toArray() as $unit) {
                if ($unit['unit_id'] === $request->get('unit_id')) {

                    if (!$unit['status']) {
                        return response()->json([
                            'errors' => 'The unit '. $request->get('unit_id') .' is deactivated.'
                        ], 401);
                    }

                    $hasUnit = true;
                    break;
                }
            }

            if (!$hasUnit) {
                return response()->json([
                    'errors' => 'This computer is not registered.'
                ], 401);
            }

            $token = auth()->user()->createToken($tokenName, ['unit'])->plainTextToken;

            return response()->json([
                'token' => $token,
                'userId' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'unit' => $request->get('unit_id')
            ]);
        }

        return response()->json([
            'errors' => 'Invalid unit login.'
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
