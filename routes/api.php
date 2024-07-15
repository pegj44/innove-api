<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\FunderController;
use App\Http\Controllers\TradingIndividuals;
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

Route::post('/auth-user', [AuthenticatedSessionController::class, 'authenticateUserToken'])->middleware(['auth:sanctum', 'ability:admin']);

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

Route::middleware(['auth:sanctum', 'ability:admin'])->group(function()
{
    Route::controller(TradingUnitsController::class)->group(function()
    {
        Route::post('/trading-unit', 'store');
        Route::post('/trading-unit/{id}', 'update');
        Route::get('/trading-units', 'getTradingUnits');
        Route::delete('trading-unit/{id}', 'destroy');

        Route::post('/trading-units/settings/set-password', 'setPassword');
        Route::post('/trading-units/settings/update-password', 'updatePassword');
        Route::get('/trading-unit/settings', 'getSettings');
    });

    Route::controller(FunderController::class)->group(function()
    {
        Route::post('funder', 'store');
        Route::get('funders', 'list');
        Route::get('funder/{id}', 'edit');
        Route::post('funder/{id}', 'update');
        Route::delete('funder/{id}', 'destroy');
    });

    Route::controller(TradingIndividuals::class)->group(function()
    {
        Route::post('trading-individual', 'store');
    });
});

Route::middleware(['auth:sanctum', 'ability:unit'])->group(function()
{
    Route::post('test-broadcast', [TradingUnitsController::class, 'testBroadcastConnection']);
});
