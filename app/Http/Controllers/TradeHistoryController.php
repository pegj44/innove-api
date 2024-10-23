<?php

namespace App\Http\Controllers;

use App\Models\TradeHistoryModel;
use App\Models\TradeReport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TradeHistoryController extends Controller
{
    public function getAllTrades(Request $request)
    {
        $data = $request->all();
        $phase = ($request->get('current_phase')) ? $data['current_phase'] : null;
        $items = \App\Models\TradeHistoryModel::with(['tradingAccountCredential.funder', 'tradingAccountCredential.tradeReports'])
            ->where('account_id', auth()->user()->account_id);

        if ($phase) {
            $items->whereHas('tradingAccountCredential', function($query) use ($phase) {
                $query->where('current_phase', $phase);
            });
        }

        if ($request->get('range')) {
            if ($data['range'] === 'currentMonth') {
                $items->whereMonth('created_at', Carbon::now()->month);
            }
        }

        $orderBy = ($request->get('orderBy'))? $data['orderBy'] : 'created_at';
        $order = ($request->get('order'))? $data['order'] : 'desc';

        $items->orderBy($orderBy, $order);

        return response()->json($items->get()->toArray());
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
            ->where('account_id', auth()->user()->account_id)
            ->orderBy('created_at', 'desc')
            ->get();

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
        $tradeHistory->save();

        return response()->json($request->all());
    }
}
