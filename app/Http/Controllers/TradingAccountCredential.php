<?php

namespace App\Http\Controllers;

use App\Models\TradeReport;
use Illuminate\Http\Request;
use App\Models\TradingAccountCredential as TradingAccountCredentialModel;
use Illuminate\Support\Facades\Validator;

class TradingAccountCredential extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function getCredentials()
    {
        $credentials = TradingAccountCredentialModel::with(['funder.metadata', 'userAccount.tradingUnit'])
            ->where('account_id', auth()->user()->account_id)
            ->get();

        return response()->json($credentials);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = array_filter($request->except('_token'));
            $validator = $this->validateUserInput($data);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data['account_id'] = auth()->user()->account_id;

            $credential = TradingAccountCredentialModel::create($data);

            if (!$credential) {
                return response()->json(['errors' => __('Failed to create the credential.')]);
            }

            $tradeReport = new TradeReport();
            $tradeReport->account_id = auth()->user()->account_id;
            $tradeReport->trade_account_credential_id = $credential->id;
            $tradeReport->starting_daily_equity = $request->get('starting_balance');
            $tradeReport->latest_equity = $request->get('starting_balance');
            $tradeReport->status = 'idle';
            $tradeReport->save();

            return response()->json(['message' => __('Successfully created credential.')]);

        } catch (\Exception $e) {
            info(print_r([
                'errorTradingAccountCredentialStore' => $e->getMessage()
            ], true));

            return response()->json(['errors' => __('Error creating credential')]);
        }
    }

    private function validateUserInput($data)
    {
        $inputsToValidate = [
            'funder_id' => ['required', 'numeric'],
            'user_account_id' => ['required'],
            'funder_account_id' => ['required'],
            'starting_balance' => ['required'],
            'asset_type' => ['required'],
            'symbol' => ['required'],
            'current_phase' => ['required'],
            'status' => ['required']
        ];

        return Validator::make($data, $inputsToValidate);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $credentials = TradingAccountCredentialModel::with(['funder.metadata', 'userAccount.metadata'])
                ->where('id', $id)
                ->where('account_id', auth()->user()->account_id)
                ->first();

            return response()->json($credentials);
        } catch (\Exception $e) {
            info(print_r([
                'errorEditCredential' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error retrieving data.')]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $data = array_filter($request->except('_token'));

            $validator = $this->validateUserInput($data);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $item = TradingAccountCredentialModel::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            if (!$item) {
                return response()->json(['errors' => __('Unable to find credential record.')]);
            }

            $item->fill($data);
            $update = $item->update();

            if (!$update) {
                return response()->json(['errors' => __('Failed to update credential.')]);
            }

            return response()->json(['message' => __('Successfully updated credential.')]);
        } catch (\Exception $e) {

            return response()->json(['errors' => __('Error updating credential.')]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $item = TradingAccountCredentialModel::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            if (!$item) {
                return response()->json(['errors' => 'Failed to remove item.']);
            }

            $item->delete();

            return response()->json([
                'message' => __('Successfully removed item.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'errorRemoveCredential' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error deleting the item.']);
        }
    }
}
