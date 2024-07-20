<?php

namespace App\Http\Controllers;

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
        $credentials = TradingAccountCredentialModel::with(['funder.metadata', 'tradingIndividual.metadata'])
            ->where('user_id', auth()->id())
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

            $data['user_id'] = auth()->id();

            $credential = TradingAccountCredentialModel::create($data);

            if (!$credential) {
                return response()->json(['errors' => __('Failed to create the credential.')]);
            }

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
            'trading_individual_id' => ['required', 'numeric'],
            'funder_id' => ['required', 'numeric'],
            'account_id' => ['required'],
            'phase' => ['required'],
            'status' => ['required']
        ];

        if (array_intersect(array_keys($data), ['dashboard_login_url', 'dashboard_login_username', 'dashboard_login_password'])) {
            $inputsToValidate['dashboard_login_url'] = ['required', 'url'];
            $inputsToValidate['dashboard_login_username'] = ['required',];
            $inputsToValidate['dashboard_login_password'] = ['required'];
        }

        if (array_intersect(array_keys($data), ['platform_login_url', 'platform_login_username', 'platform_login_password'])) {
            $inputsToValidate['platform_login_url'] = ['required', 'url'];
            $inputsToValidate['platform_login_username'] = ['required'];
            $inputsToValidate['platform_login_password'] = ['required'];
        }

        return Validator::make($data, $inputsToValidate);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $credentials = TradingAccountCredentialModel::with(['funder.metadata', 'tradingIndividual.metadata'])
                ->where('id', $id)
                ->where('user_id', auth()->id())
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

            $item = TradingAccountCredentialModel::where('id', $id)->where('user_id', auth()->id())->first();

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
            $item = TradingAccountCredentialModel::where('id', $id)->where('user_id', auth()->id())->first();

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
