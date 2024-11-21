<?php

use App\Events\UnitResponse;
use App\Events\UnitsEvent;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CalculationsController;
use App\Http\Controllers\FunderAccountCredentialController;
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
use App\Models\SubAccountsModel;
use App\Models\TradeHistoryV2Model;
use App\Models\TradeHistoryV3Model;
use App\Models\TradeReport;
use App\Models\TradingUnitQueueModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubAccountsController;
use Illuminate\Support\Str;

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

//Route::get('/remove-all-pairs', [TradePairAccountsController::class, 'removeAllAccountPairs']);

Route::post('/token/create', [AuthenticatedSessionController::class, 'createToken']);

Route::post('/auth-user', [AuthenticatedSessionController::class, 'authenticateUserToken'])->middleware(['auth:sanctum', 'ability:admin,investor']);

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

Route::middleware(['auth:sanctum', 'ability:admin,investor'])->group(function()
{
    Route::controller(\App\Http\Controllers\UserProfileController::class)->prefix('profile')->group(function()
    {
        Route::get('/{id}', 'getProfile');
        Route::post('/add', 'storeProfile');
        Route::put('/{id}', 'updateProfile');
    });

    Route::controller(\App\Http\Controllers\UserSettingsController::class)->prefix('user/settings/')->group(function()
    {
        Route::get('', 'getSettings');
        Route::post('', 'store');
        Route::put('update', 'update');
        Route::delete('{id}', 'destroy');
    });

    Route::controller(FunderController::class)->group(function()
    {
        Route::get('funders', 'list');
    });

    Route::controller(TradingIndividualsController::class)->group(function()
    {
        Route::get('trading-individuals', 'getTradingIndividuals');
    });

    Route::controller(TradingAccountCredential::class)->group(function()
    {
        Route::get('credentials', 'getCredentials');
    });

    Route::controller(TradeReportController::class)->group(function()
    {
        Route::get('trade/reports', 'getReports');
    });

    Route::controller(TradePairAccountsController::class)->group(function()
    {
        Route::get('trade/paired-items', 'getPairedItems');
    });
});
Route::middleware(['auth:sanctum', 'ability:admin'])->group(function()
{
    Route::post('dev/audit-account', [\App\Http\Controllers\DevController::class, 'auditAccount']);

    Route::controller(\App\Http\Controllers\PayoutController::class)->prefix('trade/')->group(function()
    {
        Route::post('payout', 'store');
        Route::put('payout/{id}', 'update');
    });

    Route::controller(SubAccountsController::class)->prefix('sub-account/')->group(function()
    {
        Route::get('list', 'getSubAccounts');
        Route::get('{id}', 'edit');
        Route::post('create', 'store');
        Route::patch('{id}', 'update');
        Route::delete('{id}', 'destroy');
    });

    Route::get('account/entities', [UserEntityController::class, 'getAccountEntities']);
//    Route::get('account/entities/individuals-and-funders', [UserEntityController::class, 'getUserIndividualsAndFunders']);

    Route::controller(TradePairAccountsController::class)->prefix('trade/')->group(function()
    {
        Route::get('queue', 'getQueuedItems');
//        Route::post('/starting-equity/update', 'updateStartingEquity');
//        Route::post('/starting-equity/update/status', 'updateStartingEquityJobStatus');
        Route::post('/pair-accounts', 'pairAccounts');
        Route::post('/pair-manual', 'pairManual');
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
        Route::get('funder/{id}', 'edit');
        Route::post('funder/{id}', 'update');
        Route::delete('funder/{id}', 'destroy');
    });

    Route::controller(TradingIndividualsController::class)->group(function()
    {
        Route::post('trading-individual', 'store');
        Route::get('trading-individual/{id}', 'edit');
        Route::post('trading-individual/{id}', 'update');
        Route::delete('trading-individual/{id}', 'destroy');
        Route::post('trading-individuals', 'uploadIndividuals');
    });

    Route::controller(TradingAccountCredential::class)->group(function()
    {
        Route::post('credential', 'store');
        Route::delete('credential/{id}', 'destroy');
        Route::get('credential/{id}', 'edit');
        Route::post('credential/{id}', 'update');
    });
});

Route::middleware(['auth:sanctum', 'ability:unit'])->prefix('/unit/')->group(function()
{
//    Route::controller(TradeReportController::class)->group(function()
//    {
//        Route::post('/report/close-trade', 'reportCloseTrade');
//    });

    Route::controller(TradeController::class)->group(function()
    {
        Route::post('/report/close-trade', 'closePosition');
        Route::post('/close-trade', 'closeTrade');
    });
});


Route::middleware(['auth:sanctum', 'ability:admin,unit'])->group(function()
{
    Route::controller(FunderAccountCredentialController::class)->group(function()
    {
        Route::post('credential/funder/account', 'store');
        Route::get('credential/funder/accounts', 'getFunderCredentialAccounts');
        Route::get('credential/funder/account/{id}', 'getFunderCredentialAccount');
        Route::put('credential/funder/account/{id}', 'updateFunderCredentialAccount');
        Route::delete('credential/funder/account/{id}', 'destroyFunderCredentialAccount');
    });

    Route::controller(TradeReportController::class)->group(function()
    {
        Route::get('/ongoing-trades/{id}', 'getOngoingTrades');

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
        Route::post('trade/initiate-v2', 'initiateTradeV2');
        Route::post('trade/re-initialize', 'reInitializeTrade');
        Route::post('trade/unit-ready', 'unitReady');
        Route::post('trade/position/close', 'closePosition');
        Route::post('trade/pair-units', 'pairUnits');

        Route::post('trade/error', 'tradeErrorReport');
        Route::post('trade/start', 'startTrade');
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

Route::middleware(['auth:sanctum', 'ability:admin,investor'])->group(function()
{
    Route::get('trade/history/weekly', [\App\Http\Controllers\TradeHistoryController::class, 'getWeeklyTrades']);
    Route::get('trade/history', [\App\Http\Controllers\TradeHistoryController::class, 'getAllTrades']);
    Route::get('trade/history-new', [\App\Http\Controllers\TradeHistoryController::class, 'getAllTrades_new']);
    Route::get('trade/payouts', [\App\Http\Controllers\PayoutController::class, 'getPayouts']);
    Route::get('trade/payout/{id}', [\App\Http\Controllers\PayoutController::class, 'getPayout']);
});


Route::middleware(['auth:sanctum', 'ability:admin,investor'])->group(function()
{
    Route::controller(\App\Http\Controllers\InvestorController::class)->prefix('investor')->group(function()
    {
        Route::get('ongoing-trades', 'getOngoingTrades');
    });

    Route::controller(\App\Http\Controllers\TradeHistoryController::class)->prefix('trade-history')->group(function()
    {
        Route::get('list', 'getTradeHistory');
    });
});

Route::middleware(['auth:sanctum', 'ability:admin'])->group(function()
{
    Route::controller(\App\Http\Controllers\TradeHistoryV2::class)->prefix('trade/history-v2/')->group(function()
    {
        Route::post('', 'store');
        Route::get('{id}', 'getHistoryItem');
        Route::delete('{id}', 'destroy');
    });

    Route::controller(\App\Http\Controllers\TradeHistoryController::class)->prefix('trade-history')->group(function()
    {
        Route::post('/add', 'store');
        Route::get('/{id}', 'getHistoryItem');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('dev/add-trade-history', 'devAddTradeHistory');

    });

    Route::controller(TradingAccountCredential::class)->prefix('dev/')->group(function()
    {
        Route::post('bulk-update-funder-target-profit', 'bulkUpdateFunderTargetProfit');
    });

});



Route::middleware(['auth:sanctum', 'ability:admin,unit'])->group(function()
{
    Route::post('robot/setup/tradoverse', [TradeController::class, 'setupTradoverse']);

    Route::post('/dev/trade/initiate', [\App\Http\Controllers\DevController::class, 'initiateTrade']);

    Route::post('dev', function(Request $request)
    {
        $tradeAccountId = 80;
//
//        $item = new TradeHistoryV3Model();
//        $item->trade_account_credential_id = $tradeAccountId;
//        $item->starting_daily_equity = (float) $request->get('starting_daily_equity');
//        $item->latest_equity = $request->get('latest_equity');
//        $item->status = 'phase-3';
//        $item->highest_balance = (!empty($request->get('highest_balance')))? $request->get('highest_balance') : $request->get('latest_equity');
//        $item->created_at = '2024-11-14T05:24:21.000000Z';
//        $item->updated_at = '2024-11-14T05:24:21.000000Z';
//        $item->save();
//


        $history = TradeHistoryV3Model::where('trade_account_credential_id', $tradeAccountId)->get();
        $items = [];
        foreach ($history as $item) {
            $item = $item->toArray();
            $items[$item['created_at']] = (float) $item['latest_equity'] - (float) $item['starting_daily_equity'];
        }

        !d($items);
        die();
//
//        $currentPhase = str_replace('phase-', '', $tradeAccount['trading_account_credential']['current_phase']);
//        $highestBalArr = [];
//
//        $startingBal = (float) $tradeAccount['trading_account_credential']['starting_balance'];
//        $latestEqty = (float) $tradeAccount['latest_equity'];
//        $maxDrawdown = (float) $tradeAccount['trading_account_credential']['phase_'. $currentPhase .'_max_drawdown'];
//
//        if ($tradeAccount['trading_account_credential']['drawdown_type'] === 'trailing_endofday') {
//            if (!empty($tradeAccount['trading_account_credential']['history_v3'])) {
//                foreach ($tradeAccount['trading_account_credential']['history_v3'] as $tradeItem) {
//                    $highestBalArr[] = (float) $tradeItem['highest_balance'];
//                }
//            }
//
//            $highestBal = (!empty($highestBalArr))? max($highestBalArr) : $latestEqty;
//            $bufferZone = $startingBal + $maxDrawdown;
//
//            if ($highestBal >= $bufferZone) {
//                return $latestEqty - $startingBal;
//            }
//
//            if ($highestBal <= $startingBal) {
//                $maxThreshold = $startingBal - $maxDrawdown;
//            } else {
//                $maxThreshold = $highestBal - $maxDrawdown;
//            }
//
//            return $latestEqty - $maxThreshold;
//        }
//
//        if ($tradeAccount['trading_account_credential']['drawdown_type'] === 'static') {
//            $maxTreshold = $startingBal - $maxDrawdown;
//            return $latestEqty - $maxTreshold;
//        }
//
//        return 'N/A';

//!d($tradeAccount);
die();

//        $allRecord = TradeHistoryV2Model::where('trade_account_credential_id', $tradeAccount->trade_account_credential_id)
//            ->get();
//
//        $equities = [];
//
//        foreach ($allRecord as $item) {
//            $equities[] = $item->latest_equity;
//        }
//
//        $currentPhase   = str_replace('phase-', '', $tradeAccount->tradingAccountCredential->current_phase);
//        $maxDdStr       = 'phase_'. $currentPhase .'_max_drawdown';
//        $maxDrawdown    = (float) $tradeAccount->tradingAccountCredential->$maxDdStr;
//        $highestEquity  = max($equities);
//        $maxThreshold   = (float) $highestEquity - $maxDrawdown;
//        $Rdd = (float) $tradeAccount->latest_equity - $maxThreshold;



        !d($maxThreshold);
        !d($Rdd);


        die();



//        $tradeAccount = TradeReport::with('tradingAccountCredential')
//            ->where('account_id', auth()->user()->account_id)
//            ->where('id', 41)
//            ->first();
//
//        $startOfDay = Carbon::today()->addHours(4);
//
//        $todaysRecord = TradeHistoryV2Model::where('trade_account_credential_id', $tradeAccount->tradingAccountCredential->id)
//            ->where('created_at', '>=', $startOfDay)
//            ->get();
//
//        $prevDayRecord = TradeHistoryV2Model::where('trade_account_credential_id', $tradeAccount->tradingAccountCredential->id)
//            ->where('created_at', '<', $startOfDay)
//            ->get();
//
//        !d($prevDayRecord);
        die();



//            UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-closed');
//        UnitResponse::dispatch(auth()->user()->account_id, [], $request->get('action'));



//        $id = 52;
//        $trades = [30602.00];
//        $startingBal = 30000;
//        $startingDailyEqty = $startingBal;
//
//        $dates = [
//            '2024-10-08 04:30:00',
//            '2024-10-09 04:30:00',
//            '2024-10-10 04:30:00',
//            '2024-10-11 04:30:00',
//            '2024-10-14 04:30:00',
//            '2024-10-15 04:30:00',
//            '2024-10-16 04:30:00',
//            '2024-10-17 04:30:00',
//            '2024-10-18 04:30:00',
//            '2024-10-21 04:30:00',
//            '2024-10-22 04:30:00',
//            '2024-10-23 04:30:00',
//            '2024-10-24 04:30:00',
//            '2024-10-25 04:30:00',
//        ];
//
//        foreach ($trades as $index => $trade) {
//
//            $latestEqty = $startingDailyEqty + $trade;
//
//            $item = new TradeHistoryV2Model();
//            $item->trade_account_credential_id = $id;
//            $item->starting_daily_equity = $startingDailyEqty;
//            $item->latest_equity = $latestEqty;
//            $item->status = '';
//            $item->created_at = $dates[$index];
//            $item->updated_at = $dates[$index];
//            $item->save();
//
//
//            $startingDailyEqty = $latestEqty;
//        }



//        UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-started');
//        UnitResponse::dispatch(auth()->user()->account_id, [], $request->get('action'));
//
//        echo 'trade started notification';
//        die();




// Change password

//        $email = 'odrokie@gmail.com';
//        $newPassword = 'newpasss';
//
//        // Find the user by email
//        $user = User::where('email', $email)->first();
//
//        if (!$user) {
//            $this->error('User not found!');
//            return 1;
//        }
//
//        // Update the user's password
//        $user->password = Hash::make($newPassword);
//        $user->save();
//
//        // Display a success message
//        var_dump('Password has been successfully updated for user: ' . $email);
//
//        die();

//        return response()->json(['test2' => 3]);
    });
});


//Route::post('create-permission', function()
//{
//    $role = \Spatie\Permission\Models\Role::create(['name' => 'investor']);
//    $permission = \Spatie\Permission\Models\Permission::create(['name' => 'investor']);
//
//    $role->givePermissionTo($permission);
//    $permission->assignRole($role);
//
//    return response()->json('permission created');
//});
