<?php

namespace App\Http\Controllers;

use App\Models\TradeHistoryModel;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TradeHistoryController extends Controller
{
    public function getAllTrades()
    {
        $items = \App\Models\TradeHistoryModel::with('tradingAccountCredential')
            ->where('account_id', auth()->user()->account_id)
            ->get()->toArray();

        return response()->json($items);
    }

    public function getWeeklyTrades()
    {
        $items = \App\Models\TradeHistoryModel::with('tradingAccountCredential')
            ->where('account_id', auth()->user()->account_id)
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->get()->toArray();

        return response()->json($items);
    }

    public function getTradeHistory()
    {
        $items = TradeHistoryModel::with('tradingAccountCredential.funder')
            ->where('account_id', auth()->user()->account_id)->get();

        return response()->json($items);
    }

    public function devAddTradeHistory(Request $request)
    {
        $tradeAccount = \App\Models\TradingAccountCredential::where('funder_account_id', $request->get('account_id'))->first();

        $tradeHistory = new TradeHistoryModel();
        $tradeHistory->account_id = auth()->user()->account_id;
        $tradeHistory->trade_account_credential_id = $tradeAccount->id;
        $tradeHistory->starting_daily_equity = (float) $request->get('startingDailyEquity');
        $tradeHistory->latest_equity = (float) $request->get('latestEquity');
//        $tradeHistory->created_at = $request->get('date');
//        $tradeHistory->updated_at = $request->get('date');
        $tradeHistory->save();

        return response()->json($request->all());
    }
}
