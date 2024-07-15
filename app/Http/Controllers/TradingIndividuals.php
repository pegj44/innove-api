<?php

namespace App\Http\Controllers;

use App\Models\TradingIndividual;
use App\Models\TradingIndividualMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TradingIndividuals extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->except('_token'), [
                'type' => ['required', 'regex:/^[a-zA-Z0-9-_ ]+$/'],
                'unit' => ['required', 'numeric'],
                'first_name' => ['required', 'regex:/^[a-zA-Z0-9- ]+$/'],
                'middle_name' => ['required', 'regex:/^[a-zA-Z0-9- ]+$/'],
                'last_name' => ['required', 'regex:/^[a-zA-Z0-9- ]+$/'],
                'address' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
                'city' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
                'province' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
                'zip_code' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
                'contact_number1' => ['required', 'regex:/^\+?[0-9\s\-()]*$/'],
                'contact_number2' => ['regex:/^\+?[0-9\s\-()]*$/'],
                'birth_year' => ['required', 'numeric'],
                'birth_month' => ['required', 'numeric'],
                'birth_day' => ['required', 'numeric'],
                'id_type' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
                'billing' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
                'email' => ['required', 'email']
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $individual = new TradingIndividual();

            $individual->user_id = auth()->id();
            $individual->trading_unit_id = $request->get('unit');
            $newItem = $individual->save();

            if (!$newItem) {
                return response()->json(['errors' => __('Failed to create record')]);
            }

            $individualMetadata = [];

            foreach ($request->except(['_token']) as $key => $value) {
                $individualMetadata[] = [
                    'trading_individual_id' => $individual->id,
                    'key' => strip_tags($key),
                    'value' => strip_tags($value)
                ];
            }

            TradingIndividualMetadata::insert($individualMetadata);

            return response()->json(['message' => __('Successfully created item')]);
        } catch (\Exception $e) {
            info(print_r([
                'errorIndividualCreate' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error creating a record')]);
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
