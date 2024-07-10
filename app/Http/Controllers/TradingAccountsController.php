<?php

namespace App\Http\Controllers;

use App\Models\TradingAccountsModel;
use Illuminate\Http\Request;

class TradingAccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $account = new TradingAccountsModel($request->only([
            'ip_address',
            'funder',
            'account',
            'initial',
            'phase',
            'starting_balance',
            'starting_daily_equity',
            'latest_equity',
            'status',
            'target_profit',
            'remarks'
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
