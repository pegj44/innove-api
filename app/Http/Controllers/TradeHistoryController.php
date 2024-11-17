<?php

namespace App\Http\Controllers;

use App\Models\TradeHistoryModel;
use App\Models\TradeReport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TradeHistoryController extends Controller
{
    public function destroy(string $id)
    {
        try {
            $item = TradeHistoryModel::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            if (!$item) {
                return response()->json(['errors' => 'Failed to remove trade history.']);
            }

            $item->delete();

            return response()->json([
                'message' => __('Successfully removed trade history.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'errorRemoveTradeHistory' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error deleting the trade history.']);
        }
    }

    public function getHistoryItem(string $id)
    {
        try {
            $item = TradeHistoryModel::with('tradingAccountCredential.tradeReports')
                ->where('id', $id)
                ->where('account_id', auth()->user()->account_id)->first();

            return response()->json($item);
        } catch (\Exception $e) {
            info(print_r([
                'errorHistory' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error retrieving data.')]);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $item = TradeHistoryModel::where('account_id', auth()->user()->account_id)
                    ->where('id', $id)
                    ->first();

            $item->trade_account_credential_id = $request->get('trade_account_credential_id');
            $item->latest_equity = $request->get('latest_equity');
            $update = $item->update();

            if (!$update) {
                return response()->json(['errors' => __('Failed to update the trade history.')]);
            }

            return response()->json(['message' => __('Successfully updated trade history.')]);
        } catch (\Exception $e) {
            return response()->json(['errors' => 'Error updating the trade history.']);
        }
    }

    public function store(Request $request)
    {
        $item = \App\Models\TradingAccountCredential::with('tradeReports')
            ->where('account_id', auth()->user()->account_id)
            ->where('id', $request->get('trade_account_credential_id'))
            ->first();

        if (empty($item)) {
            return response()->json(['error' => __('Trading account not found.')]);
        }

        $newItem = new TradeHistoryModel();
        $newItem->account_id = auth()->user()->account_id;
        $newItem->trade_account_credential_id = $request->get('trade_account_credential_id');
        $newItem->starting_daily_equity = (float) $item->tradeReports->starting_daily_equity;
        $newItem->latest_equity = (float) $request->get('latest_equity');
        $newItem->save();



        return response()->json(['message' => __('Successfully added trade history.')]);
    }

    public function getAllTrades_new(Request $request)
    {
        $data = $request->all();
        $phase = ($request->get('current_phase')) ? $data['current_phase'] : null;

        $items = TradeReport::with(['tradingAccountCredential.historyV3', 'tradingAccountCredential.funder'])
            ->where('status', '<>', 'breached')
            ->where('account_id', auth()->user()->account_id);

        if ($phase) {
            $items->whereHas('tradingAccountCredential', function($query) use ($phase) {
                $query->where('current_phase', $phase);
            });
        }

        if ($request->get('range')) {
            if ($data['range'] === 'currentMonth') {
                $currentMonth = Carbon::now()->month;
                $items->whereHas('tradingAccountCredential.historyV3', function($query) use ($currentMonth) {
                    $query->whereMonth('created_at', $currentMonth);
                });
            }
        }

        $orderBy = ($request->get('orderBy'))? $data['orderBy'] : 'created_at';
        $order = ($request->get('order'))? $data['order'] : 'desc';

        $items->orderBy($orderBy, $order);


//        $startOfMonth = Carbon::now('Asia/Manila')->startOfMonth()->setTimezone('UTC');
//        $endOfMonth = Carbon::now('Asia/Manila')->endOfMonth()->setTimezone('UTC');
//
//        $items = TradeReport::with(['tradingAccountCredential' => function ($query) use ($startOfMonth, $endOfMonth) {
//            $query->with(['historyV3' => function ($query) use ($startOfMonth, $endOfMonth) {
//                // Load only the historyV3 records created within the current month
//                $query->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
//            }]);
//        }])
//            ->whereHas('tradingAccountCredential.historyV3', function ($query) use ($startOfMonth, $endOfMonth) {
//                // Ensure TradeReport items are included only if they have historyV3 records within the date range
//                $query->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
//            });

        return response()->json($items->get()->toArray());
    }

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
