<?php

namespace App\Http\Controllers;

use App\Jobs\TradeIndividualAddRowJob;
use App\Models\TradingIndividual;
use App\Models\TradingIndividualMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'first_name' => $item->first_name,
                'middle_name' => $item->middle_name,
                'last_name' => $item->last_name,
                'email' => $item->email,
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

    private static function validateInputs($data)
    {
        $fields = [
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
            'birth_year' => ['required', 'numeric'],
            'birth_month' => ['required', 'numeric'],
            'birth_day' => ['required', 'numeric'],
            'id_type' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
            'billing' => ['required', 'regex:/^[a-zA-Z0-9\s,.\-()]+$/'],
            'email' => ['required', 'email']
        ];

        if (!empty($data['contact_number2'])) {
            $fields['contact_number2'] = ['regex:/^\+?[0-9\s\-()]*$/'];
        }

        return Validator::make($data, $fields);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return self::save(auth()->id(), $request->except('_token'));
    }

    public static function save($user_id, $data)
    {
        try {
            $validator = self::validateInputs($data);

            if ($validator->fails()) {
                return response()->json(['validation_error' => $validator->errors()], 422);
            }

            $individual = new TradingIndividual();

            $individual->user_id = $user_id;
            $individual->trading_unit_id = $data['unit'];
            $individual->first_name = $data['first_name'];
            $individual->middle_name = $data['middle_name'];
            $individual->last_name = $data['last_name'];
            $individual->email = $data['email'];
            $individual->metaData = $data;

            $newItem = $individual->save();

            if (!$newItem) {
                return response()->json(['errors' => __('Failed to create record')]);
            }

            return response()->json(['message' => __('Successfully created item')]);
        } catch (\Exception $e) {
            info(print_r([
                'errorIndividualCreate' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error creating a record')]);
        }
    }

    public function uploadIndividuals(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only('file'), [
            'file' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $file = $request->file('file');
        $filePath = $file->store('uploads');
        $csvData = [];

        if (($handle = fopen(storage_path('app/' . $filePath), 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $csvData[] = $data;
            }
            fclose($handle);
        }

        $header = array_shift($csvData);
        $csvData = array_map(function ($row) use ($header) {
            return array_combine($header, $row);
        }, $csvData);

        if (empty($csvData)) {
            return response()->json(['errors' => __('File is empty')]);
        }

        Storage::delete($filePath);

        $unitsArr = TradingUnitsController::getUnitsArray();

        if (empty($unitsArr)) {
            return response()->json(['errors' => __('No units added.')]);
        }

        $user_id = auth()->id();

        foreach ($csvData as $item) {
//            self::uploadRowItem($user_id, $item, $unitsArr);
            TradeIndividualAddRowJob::dispatch($user_id, $item, $unitsArr);
        }

        return response()->json(['message' => __('Uploading data..')]);
    }

    public static function uploadRowItem($user_id, $data, $units)
    {
        if (empty($data)) {
            return false;
        }

        $formattedData = [];

        foreach ($data as $key => $value) {
            $dbKey = trim(strtolower($key));
            $dbKey = str_replace(' ', '_', $dbKey);

            $formattedData[$dbKey] = $value;

            if ($dbKey === 'unit' && isset($units[$value])) {
                $formattedData[$dbKey] = $units[$value];
            } else {
                unset($data[$key]);
            }
        }

        if (empty($formattedData)) {
            return false;
        }

        $response = self::save($user_id, $formattedData);
        $response = $response->getData();

        return (!isset($response->errors));
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
                'first_name' => $item->first_name,
                'middle_name' => $item->middle_name,
                'last_name' => $item->last_name,
                'email' => $item->email,
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
            $data = $request->except(['_token']);
            $validator = self::validateInputs($data);

            if ($validator->fails()) {
                return response()->json(['validation_error' => $validator->errors()], 422);
            }

            $individual = TradingIndividual::where('id', $id)->where('user_id', auth()->id())->first();

            $individual->trading_unit_id = $data['unit'];
            $individual->first_name = $data['first_name'];
            $individual->middle_name = $data['middle_name'];
            $individual->last_name = $data['last_name'];
            $individual->email = $data['email'];
            $individual->metaData = $data;

            $success = $individual->update();

            if (!$success) {
                return response()->json(['errors' => __('Failed to update the item.')]);
            }

            $individual->updateMetadata($individual);

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
