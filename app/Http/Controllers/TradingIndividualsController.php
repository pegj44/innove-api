<?php

namespace App\Http\Controllers;

use App\Models\TradingIndividual;
use App\Models\TradingIndividualMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TradingIndividualsController extends Controller
{
    public function getTradingIndividuals()
    {
        $items = TradingIndividual::with('metadata', 'tradingUnit')
            ->where('user_id', auth()->id())
            ->get();

        $itemsWithMetadata = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'trading_unit' => [
                    'id' => $item->tradingUnit->id,
                    'name' => $item->tradingUnit->name,
                    'ip_address' => $item->tradingUnit->ip_address,
                ],
                'metadata' => $item->metadata->pluck('value', 'key')->toArray(),
            ];
        })->toArray();

        return response()->json($itemsWithMetadata);
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
                    'value' => (!empty($value))? strip_tags($value) : ''
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

    public function edit(string $id)
    {
        $items = TradingIndividual::with('metadata', 'tradingUnit')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->get();

        $itemsWithMetadata = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'trading_unit' => [
                    'id' => $item->tradingUnit->id,
                    'name' => $item->tradingUnit->name,
                    'ip_address' => $item->tradingUnit->ip_address,
                ],
                'metadata' => $item->metadata->pluck('value', 'key')->toArray(),
            ];
        })->first();

        return response()->json($itemsWithMetadata);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $rawData = $request->except(['_token']);
            $data = [];

            $individual = TradingIndividual::find($id);

            if ($individual->trading_unit_id != $rawData['unit']) {
                $individual->trading_unit_id = $rawData['unit'];
                $individual->update();
            }

            foreach ($rawData as $key => $value) {
                $data[] = [
                    'trading_individual_id' => $id,
                    'key' => strip_tags($key),
                    'value' => (!empty($value))? strip_tags($value) : ''
                ];
            }

            $success = TradingIndividualMetadata::upsert($data, uniqueBy: ['trading_individual_id', 'key'], update: ['value']);

            if (!$success) {
                return response()->json(['errors' => __('Failed to update the item.')]);
            }

            return response()->json(['message' => __('Successfully updated Item.')]);

        } catch (\Exception $e) {
            info(print_r([
                'errorUpdateTradingIndividual' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error updating the item record.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $item = TradingIndividual::find($id);

            if (!$item) {
                return response()->json(['errors' => 'Failed to remove item.']);
            }

            $item->delete();

            return response()->json([
                'message' => __('Successfully removed item.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'errorRemoveTradingIndividual' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error deleting the item.']);
        }
    }
}
