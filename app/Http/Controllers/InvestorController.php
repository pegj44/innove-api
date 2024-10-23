<?php

namespace App\Http\Controllers;

use App\Models\PairedItems;
use App\Models\TradeReport;
use Illuminate\Http\Request;

class InvestorController extends Controller
{
    public function getOngoingTrades(Request $request)
    {
        $phase = $request->get('current_phase');
        $items = TradeReport::with(['tradingAccountCredential.userAccount.tradingUnit', 'tradingAccountCredential.funder.metadata'])
            ->where('account_id', auth()->user()->account_id)
            ->where('status', 'trading');

        if ($phase) {
            $items->whereHas('tradingAccountCredential', function($query) use ($phase) {
                $query->where('current_phase', $phase);
            });
        }

        return response()->json($items->get());
    }
//
//    public function getOngoingTrades(Request $request)
//    {
//        $phase = $request->get('current_phase');
//        $items = PairedItems::with([
//            'tradeReportPair1.tradingAccountCredential.userAccount.tradingUnit',
//            'tradeReportPair1.tradingAccountCredential.funder.metadata',
//            'tradeReportPair2.tradingAccountCredential.userAccount.tradingUnit',
//            'tradeReportPair2.tradingAccountCredential.funder.metadata'
//        ])
//        ->where('account_id', auth()->user()->account_id)
//        ->where('status', 'trading');
//
//        if ($phase) {
//            $items->whereHas('tradeReportPair1.tradingAccountCredential', function($query) use ($phase) {
//                $query->where('current_phase', $phase);
//            });
//            $items->whereHas('tradeReportPair2.tradingAccountCredential', function($query) use ($phase) {
//                $query->where('current_phase', $phase);
//            });
//        }
//
//        return response()->json($items->get());
//    }
}
