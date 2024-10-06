<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CalculationsController;
use App\Http\Controllers\FunderController;
use App\Http\Controllers\MachinesController;
use App\Http\Controllers\PusherController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TradePairAccountsController;
use App\Http\Controllers\TradeReportController;
use App\Http\Controllers\TradingAccountCredential;
use App\Http\Controllers\TradingIndividualsController;
use App\Http\Controllers\TradingUnitsController;
use App\Http\Controllers\UserEntityController;
use App\Models\AccountsPairingJob;
use App\Models\FundersMetadata;
use App\Models\TradeReport;
use App\Models\TradingUnitQueueModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
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

Route::post('/token/create', [AuthenticatedSessionController::class, 'createToken']);

Route::post('/auth-user', [AuthenticatedSessionController::class, 'authenticateUserToken'])->middleware(['auth:sanctum', 'ability:admin']);

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/unit-login', [AuthenticatedSessionController::class, 'loginUnit'])
    ->middleware('guest')
    ->name('unit-login');

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
    Route::get('account/entities', [UserEntityController::class, 'getAccountEntities']);
//    Route::get('account/entities/individuals-and-funders', [UserEntityController::class, 'getUserIndividualsAndFunders']);

    Route::controller(TradePairAccountsController::class)->prefix('trade/')->group(function()
    {
//        Route::post('/starting-equity/update', 'updateStartingEquity');
//        Route::post('/starting-equity/update/status', 'updateStartingEquityJobStatus');
        Route::post('/pair-accounts', 'pairAccounts');
        Route::post('/pair-manual', 'pairManual');
        Route::get('/paired-items', 'getPairedItems');
        Route::post('/update-trade-report-settings', 'updateTradeSettings');
        Route::delete('/paired-items', 'clearPairedItems');
        Route::delete('/pair/{id}/remove', 'removePair');
    });

    Route::controller(TradingUnitsController::class)->group(function()
    {
        Route::post('/trading-unit', 'store');
        Route::post('/trading-unit/{id}', 'update');
        Route::get('/trading-units', 'getTradingUnits');
        Route::get('/trading-units-array', 'getUnitsArray');
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

    Route::controller(TradingIndividualsController::class)->group(function()
    {
        Route::get('trading-individuals', 'getTradingIndividuals');
        Route::post('trading-individual', 'store');
        Route::get('trading-individual/{id}', 'edit');
        Route::post('trading-individual/{id}', 'update');
        Route::delete('trading-individual/{id}', 'destroy');
        Route::post('trading-individuals', 'uploadIndividuals');
    });

    Route::controller(TradingAccountCredential::class)->group(function()
    {
        Route::get('credentials', 'getCredentials');
        Route::post('credential', 'store');
        Route::delete('credential/{id}', 'destroy');
        Route::get('credential/{id}', 'edit');
        Route::post('credential/{id}', 'update');
    });
});

Route::middleware(['auth:sanctum', 'ability:admin,unit'])->group(function()
{
    Route::controller(TradeReportController::class)->group(function()
    {
        Route::get('trade/reports', 'getReports');
        Route::post('trade/report', 'store');
        Route::post('trade/report/latest-equity/update', 'updateLatestEquity');

        Route::get('trade/report/{id}', 'edit');
        Route::post('trade/report/{id}', 'update');
        Route::patch('trade/report/updateByFunderAccount', 'updateByFunderAccount');
        Route::delete('trade/report/{id}', 'destroy');
    });

    Route::controller(TradeController::class)->group(function()
    {
        Route::post('trade/initiate', 'initiateTrade');
        Route::post('trade/unit-ready', 'unitReady');
        Route::post('trade/position/close', 'closePosition');
    });

    Route::controller(CalculationsController::class)->prefix('calculate')->group(function()
    {
       Route::post('tp-sl', 'calculateTpSl');
    });
});

Route::middleware(['auth:sanctum'])->group(function()
{
    Route::post('pusher-auth', [PusherController::class, 'authenticateUnit']);
//    Route::post('test-broadcast', [TradingUnitsController::class, 'testBroadcastConnection']);
});



Route::middleware(['auth:sanctum', 'ability:admin,unit'])->group(function()
{
    Route::post('register-machines', [MachinesController::class, 'registerMachines']);
    Route::get('machines', [MachinesController::class, 'getMachines']);
    Route::post('machine/use', [MachinesController::class, 'recordUsage']);
});

Route::post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});

Route::middleware(['auth:sanctum', 'ability:admin,unit'])->group(function()
{
    Route::get('test', function()
    {
//        $machines = MachinesController::getAvailableMachine(3, '120.28.220.253');

//        $items = \App\Models\TradeReport::with('tradingAccountCredential.tradingIndividual.tradingUnit', 'tradingAccountCredential.funder.metadata')
//            ->where('user_id', auth()->id())
//            ->whereHas('tradingAccountCredential', function ($query) {
//                $query->whereIn('account_id', ['14178', 'UPTN258956']);
//            })
//            ->where('status', 'idle')
//            ->get();
//
//        foreach ($items as $item) {
//            $funderMeta = $item['tradingAccountCredential']['funder']['metadata'];
//            $pipsCalculationType = '';
//            $pips = 1; // default pips
//
//            foreach ($funderMeta as $meta) {
//                if ($meta->key === 'pips_calculation_type') {
//                    $pipsCalculationType = $meta->value;
//                }
//            }
//
//            if ($pipsCalculationType === 'volume') {
//                $pips = CalculationsController::calculateVolume($item->latest_equity, 5.1);
//            }
//
//            var_dump($pips);
//        }


//        $funderMeta = FundersMetadata::where('funder_id', 15)
//            ->where('key', 'purchase_type')->first();

//        $output = TradePairAccountsController::getCalculatedOrderAmount('300', 'fixed', '30400', '30400', 'currency');

//        dd($output);

//        \App\Models\Funder::where('user_id', auth()->id())->delete();

        $item1 = TradeReport::where('id', 16)->first();
        $item2 = TradeReport::where('id', 17)->first();

        !d($item1->status, $item2->status);

        die();

//        return response()->json(['test2' => 3]);
    });
});


//Route::post('create-permission', function()
//{
//    $role = \Spatie\Permission\Models\Role::create(['name' => 'unit']);
//    $permission = \Spatie\Permission\Models\Permission::create(['name' => 'unit']);
//
//    $role->givePermissionTo($permission);
//    $permission->assignRole($role);
//});
