<?php

use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return 'Welcome';
});

Route::middleware(['auth'])->controller(UnitController::class)->group(function ()
{
    Route::get('unit', 'main')->name('main');
});

require __DIR__.'/auth.php';
