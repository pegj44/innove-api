<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\TradingUnitsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/auth-user', [AuthenticatedSessionController::class, 'authenticateUserToken'])->middleware('auth_api');

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.store');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');


//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

//Route::middleware('auth:sanctum')->group(function ()
//{
//    Route::controller(TradingUnitsController::class)->group(function()
//    {
//        Route::post('/trading-unit', 'store')->name('trading-unit.create');
//        Route::get('/trading-units', 'getTradingUnits')->name('trading-units');
//    });
//});

//Route::post('/auth-user', [AuthenticatedSessionController::class, 'authenticateUserToken'])->middleware('auth_api');
//
//Route::post('/register', [RegisteredUserController::class, 'store']);
//Route::post('/login', [AuthenticatedSessionController::class, 'store']);
//Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
//    ->middleware('auth:sanctum');


Route::middleware('auth:sanctum')->controller(TradingUnitsController::class)->group(function ()
{
    Route::post('/trading-unit', 'store');
    Route::post('/trading-unit/{id}', 'update');
    Route::get('/trading-units', 'getTradingUnits');
});
