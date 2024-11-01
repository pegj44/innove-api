<?php

namespace App\Http\Controllers;

use App\Models\TradeHistoryV2Model;
use Illuminate\Http\Request;

class TradeHistoryV2 extends Controller
{
    public function getHistoryItem(string $id)
    {

    }

    public function store(Request $request)
    {
        $item = new TradeHistoryV2Model();
        $item->trade_account_credential_id = $request->get('trade_account_credential_id');
        $item->starting_daily_equity = $request->get('starting_daily_equity');
        $item->latest_equity = $request->get('latest_equity');
        $item->status = (!empty($request->get('status')))? $request->get('status') : '';

        if (!empty($request->get('date'))) {
            $date = $request->get('date');
            $item->created_at = $date;
            $item->updated_at = $date;
        }

        $item->save();

        return response()->json($request->all());
    }

    public function destroy(string $id)
    {

    }
}
