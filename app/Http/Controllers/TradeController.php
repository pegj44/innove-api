<?php

namespace App\Http\Controllers;

use App\Events\UnitsEvent;
use App\Models\PairedItems;
use App\Models\TradeReport;
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

    public function closePosition(Request $request)
    {

    }

    public function unitReady(Request $request)
    {
        info(print_r([
            'unitReady' => $request->all()
        ], true));

        $hasUnitMatch = TradingUnitQueueModel::where('account_id', auth()->user()->account_id)
            ->where('queue_id', $request->get('queue_id'))->first();

        if ($hasUnitMatch) {

            $currentUtcTime = Carbon::now('UTC');
            $futureTime = $currentUtcTime->addSeconds(10);
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

            UnitsEvent::dispatch(auth()->user()->account_id, $args, 'do-trade', $hasUnitMatch->machine, $hasUnitMatch->unit);

            $args['purchase_type'] = ($hasUnitMatch->purchase_type === 'sell')? 'buy' : 'sell';
            $args['machine'] = $request->get('machine');
            UnitsEvent::dispatch(auth()->user()->account_id, $args, 'do-trade', $request->get('machine'), $request->get('unit'));

            $hasUnitMatch->delete();
        } else {
            $newQueue = new TradingUnitQueueModel();
            $newQueue->account_id = auth()->user()->account_id;
            $newQueue->unit = $request->get('unit');
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
        $pairId = $data['paired_id'];

        unset($data['paired_id']);

        foreach ($data as $item) {

            $isUnitConnected = PusherController::checkUnitConnection(auth()->user()->account_id, $item['unit']);

            if (!$isUnitConnected) {
                return response()->json([
                    'error' => 'Unit '. $item['unit'] .' is not connected.'
                ]);
            } else {

                UnitsEvent::dispatch(auth()->id(), [
                    'account_id' => $item['account_id'],
                    'latest_equity' => $item['latest_equity'],
                    'purchase_type' => $item['purchase_type'],
                    'symbol' => $item['symbol'],
                    'order_amount' => $item['order_amount'],
                    'take_profit_ticks' => $item['take_profit_ticks'],
                    'stop_loss_ticks' => $item['stop_loss_ticks'],
                    'queue_id' => $queueId,
                    'machine' => $item['machine'],
                    'unit' => $item['unit']
                ], 'initiate-trade', $item['machine'], $item['unit']);
            }
        }

        $pairedItems = PairedItems::where('id', $pairId)
            ->where('account_id', auth()->user()->account_id)->first();

        $pairedItems->status = 'trading';
        $pairedItems->update();


        $unit1 = TradeReport::where('id', $data['unit1']['id'])->where('account_id', auth()->user()->account_id)->first();
        $unit1->purchase_type = $data['unit1']['purchase_type'];
        $unit1->order_amount = $data['unit1']['order_amount'];
        $unit1->take_profit_ticks = $data['unit1']['take_profit_ticks'];
        $unit1->stop_loss_ticks = $data['unit1']['stop_loss_ticks'];
        $unit1->status = 'trading';
        $unit1->update();

        $unit2 = TradeReport::where('id', $data['unit2']['id'])->where('account_id', auth()->user()->account_id)->first();
        $unit2->purchase_type = $data['unit2']['purchase_type'];
        $unit2->order_amount = $data['unit2']['order_amount'];
        $unit2->take_profit_ticks = $data['unit2']['take_profit_ticks'];
        $unit2->stop_loss_ticks = $data['unit2']['stop_loss_ticks'];
        $unit2->status = 'trading';
        $unit2->update();

        return response()->json(['message' => __('Initiating unit trade.')]);
    }

    private function generateQueueId()
    {
        $uuid = (string) Str::uuid();
        $currentDateTime = Carbon::now()->format('Ymd_His');

        return $uuid . '_' . $currentDateTime;
    }
}
