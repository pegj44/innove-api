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
    public function index()
    {
        //
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

            $inputsToValidate = [
                'trading_individual_id' => ['required', 'numeric'],
                'funder_id' => ['required', 'numeric'],
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

            $validator = Validator::make($data, $inputsToValidate);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data['user_id'] = auth()->id();
            $data['status'] = '';

//            return response()->json($data);
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
