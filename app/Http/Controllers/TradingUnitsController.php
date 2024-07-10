<?php

namespace App\Http\Controllers;

use App\Models\TradingUnitsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TradingUnitsController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $userId = auth()->id();

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('trading_units', 'name')->where(function ($query) use ($userId) {
                        return $query->where('user_id', $userId);
                    })
                ],
                'ip_address' => [
                    'required',
                    'ip',
                    Rule::unique('trading_units', 'ip_address')->where(function ($query) use ($userId) {
                        return $query->where('user_id', $userId);
                    })
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $unit = new TradingUnitsModel();

            $unit->user_id = $userId;
            $unit->name = $request->get('name');
            $unit->ip_address = $request->get('ip_address');
            $unit->status = $request->get('status');

            $unitSaved = $unit->save();

            if (!$unitSaved) {
                return response()->json([
                    'errors' => __('Unable to create the trading unit.')
                ]);
            }

            $user = Auth::user();
            $tokenName = $unit->ip_address .'_unit';

            $user->createToken($tokenName, ['unit'])->plainTextToken;

            return response()->json([
                'unit_id' => $unit->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'errors' => __('Unable to create a unit.')
            ], 400);
        }
    }

    public function getTradingUnits()
    {
        $units = TradingUnitsModel::where('user_id', auth()->id())->get();

        return response()->json($units);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

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
        try {
            $unit = TradingUnitsModel::find($id);

            if (!$unit) {
                return response()->json([
                    'errors' => __('Unit not found.')
                ]);
            }

            $unit->name = $request->get('name');
            $unit->ip_address = $request->get('ip_address');
            $unit->status = $request->get('status');

            $unitSaved = $unit->save();

            if (!$unitSaved) {
                return response()->json([
                    'errors' => __('Failed to save the trading unit.')
                ]);
            }

            return response()->json([
                'message' => __('Success')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => __('Failed to save the trading unit.')
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
