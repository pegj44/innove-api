<?php

namespace App\Http\Controllers;

use App\Models\PayoutModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayoutController extends Controller
{
    public function getPayout(string $id)
    {
        $item = PayoutModel::where('account_id', auth()->user()->account_id)
            ->where('id', $id)
            ->first();

        return response()->json($item);
    }

    public function getPayouts()
    {
        $items = PayoutModel::with(['tradingAccountCredential.tradeReports', 'tradingAccountCredential.funder'])
            ->where('account_id', auth()->user()->account_id)
            ->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trade_account_credential_id' => ['required', 'numeric'],
            'amount_requested' => ['required', 'numeric'],
            'status' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json(['validation_error' => $validator->errors()], 422);
        }

        $item = new PayoutModel();
        $item->account_id = auth()->user()->account_id;
        $item->trade_account_credential_id = $request->get('trade_account_credential_id');
        $item->amount_requested = $request->get('amount_requested');
        $item->status = $request->get('status');
        $item->save();

        return response()->json(['message' => __('Successfully created payout request.')]);
    }

    public function update(Request $request, string $id)
    {
        try {
            $data = array_filter($request->all());

            $validator = Validator::make($request->all(), [
                'trade_account_credential_id' => ['required', 'numeric'],
                'amount_requested' => ['required', 'numeric'],
                'status' => ['required']
            ]);

            if ($validator->fails()) {
                return response()->json(['validation_error' => $validator->errors()], 422);
            }

            $item = PayoutModel::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            if (!$item) {
                return response()->json(['errors' => __('Unable to find payout record.')]);
            }

            $item->fill($data);
            $update = $item->update();

            if (!$update) {
                return response()->json(['errors' => __('Failed to update payout.')]);
            }

            return response()->json(['message' => __('Successfully updated payout.')]);
        } catch (\Exception $e) {

            return response()->json(['errors' => __('Error updating payout.')]);
        }
    }
}
