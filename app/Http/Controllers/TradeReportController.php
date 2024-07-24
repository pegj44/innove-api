<?php

namespace App\Http\Controllers;

use App\Models\TradeReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TradeReportController extends Controller
{
    public function getReports()
    {
        $items = TradeReport::with('unit', 'funder.metadata', 'tradeCredential')
            ->where('user_id', auth()->id())->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        try {
            $data = array_filter($request->except('_token'));
            $validator = $this->validateUserInput($data);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data['user_id'] = auth()->id();
            $credential = TradeReport::create($data);

            if (!$credential) {
                return response()->json(['errors' => __('Failed to create trade report.')]);
            }

            return response()->json(['message' => __('Successfully created trade report.')]);
        } catch (\Exception $e) {
            info(print_r([
                'errorCreateTradeReport' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error creating trade report.')]);
        }
    }

    private function validateUserInput($data)
    {
        $inputsToValidate = [
//            'trading_unit_id' => ['required', 'numeric'],
//            'funder_id' => ['required', 'numeric'],
            'trade_account_credential_id' => ['required', 'numeric'],
            'starting_balance' => ['required', 'numeric'],
            'starting_equity' => ['required', 'numeric'],
            'latest_equity' => ['required', 'numeric'],
            'status' => ['regex:/^[a-zA-Z0-9- ]+$/'],
        ];

        return Validator::make($data, $inputsToValidate);
    }

    public function edit(string $id)
    {
        try {
            $items = TradeReport::with('unit', 'funder.metadata', 'tradeCredential')
                ->where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            return response()->json($items);
        } catch (\Exception $e) {
            info(print_r([
                'errorEditCredential' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error retrieving data.')]);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $data = array_filter($request->except('_token'));

            $validator = $this->validateUserInput($data);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $item = TradeReport::where('id', $id)->where('user_id', auth()->id())->first();

            if (!$item) {
                return response()->json(['errors' => __('Unable to find trade report.')]);
            }

            $item->fill($data);
            $update = $item->update();

            if (!$update) {
                return response()->json(['errors' => __('Failed to update trade report.')]);
            }

            return response()->json(['message' => __('Successfully updated trade report.')]);
        } catch (\Exception $e) {
            info(print_r([
                'errorUpdateTradeReport' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error updating trade report.')]);
        }
    }

    public function destroy(string $id)
    {
        try {
            $item = TradeReport::where('id', $id)->where('user_id', auth()->id())->first();

            if (!$item) {
                return response()->json(['errors' => 'Failed to remove trade report.']);
            }

            $item->delete();

            return response()->json([
                'message' => __('Successfully removed trade report.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'errorRemoveTradeReport' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error deleting the trade report.']);
        }
    }
}
