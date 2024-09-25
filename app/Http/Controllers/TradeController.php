<?php

namespace App\Http\Controllers;

use App\Events\UnitsEvent;
use App\Models\PairedItems;
use App\Models\TradingUnitQueueModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TradeController extends Controller
{
    public $stopLossPips = 5.1;
    public $takeProfitPips = 4.9;
    public $mandatoryStopLossPercentage = 0.02;
    public $volumeMultiplierPercentage = 0.8;

    public function unitReady(Request $request)
    {
        $hasUnitMatch = TradingUnitQueueModel::where('user_id', auth()->id())
            ->where('queue_id', $request->get('queue_id'))->first();

        if ($hasUnitMatch) {

            $currentUtcTime = Carbon::now('UTC');
            $futureTime = $currentUtcTime->addSeconds(15);
            $args = [
                'year' => $futureTime->format('Y'),
                'month' => $futureTime->format('m'),
                'day' => $futureTime->format('d'),
                'hours' => $futureTime->format('H'),
                'minutes' => $futureTime->format('i'),
                'seconds' => $futureTime->format('s'),
                'purchase_type' => $hasUnitMatch->purchase_type,
                'machine' => $hasUnitMatch->machine
            ];

            UnitsEvent::dispatch(auth()->id(), $args, 'do-trade', $hasUnitMatch->machine, $hasUnitMatch->ip_address);

            $args['purchase_type'] = ($hasUnitMatch->purchase_type === 'sell')? 'buy' : 'sell';
            $args['machine'] = $request->get('machine');
            UnitsEvent::dispatch(auth()->id(), $args, 'do-trade', $request->get('machine'), $request->get('ip_address'));

            $hasUnitMatch->delete();
        } else {
            $newQueue = new TradingUnitQueueModel();
            $newQueue->user_id = auth()->id();
            $newQueue->ip_address = $request->get('ip_address');
            $newQueue->machine = $request->get('machine');
            $newQueue->queue_id = $request->get('queue_id');
            $newQueue->purchase_type = $request->get('purchase_type');
            $newQueue->save();
        }
    }

    public function initiateTrade(Request $request)
    {
        $queueId = $this->generateQueueId();
        $data = $request->except('_token');
//
//        foreach ($data as $item) {
//
//            $isUnitConnected = PusherController::checkUnitConnection(auth()->id(), $item['ip']);
//
//            if (!$isUnitConnected) {
//                return response()->json([
//                    'error' => 'Unit with IP '. $item['ip'] .' is not connected.'
//                ]);
//            } else {
//
//                UnitsEvent::dispatch(auth()->id(), [
//                    'account_id' => $item['account_id'],
//                    'latest_equity' => $item['latest_equity'],
//                    'purchase_type' => $item['purchase_type'],
//                    'order_amount' => $item['order_amount'],
//                    'take_profit_ticks' => $item['take_profit_ticks'],
//                    'stop_loss_ticks' => $item['stop_loss_ticks'],
//                    'queue_id' => $queueId,
//                    'machine' => $item['machine'],
//                    'ip_address' => $item['ip']
//                ], 'initiate-trade', $item['machine'], $item['ip']);
//            }
//        }

        $pairedItems = PairedItems::where('id', $data['paired_id'])
            ->where('user_id', auth()->id())->first();

        $pairedItems->status = 'trading';
        $pairedItems->update();

        return response()->json(['message' => __('Initiating unit trade.')]);
    }

    private function generateQueueId()
    {
        $uuid = (string) Str::uuid();
        $currentDateTime = Carbon::now()->format('Ymd_His');

        return $uuid . '_' . $currentDateTime;
    }
}
