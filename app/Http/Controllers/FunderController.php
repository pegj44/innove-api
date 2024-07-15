<?php

namespace App\Http\Controllers;

use App\Models\Funder;
use App\Models\FundersMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FunderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function list()
    {
        $funders = Funder::with('metadata')
            ->where('user_id', auth()->id())
            ->get();

        $fundersWithMetadata = $funders->map(function ($funder) {
            return [
                'id' => $funder->id,
                'user_id' => $funder->user_id,
                'created_at' => $funder->created_at,
                'updated_at' => $funder->updated_at,
                'metadata' => $funder->metadata->pluck('value', 'key')->toArray(),
            ];
        })->toArray();

        return response()->json($fundersWithMetadata);
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
            Validator::extend('valid_time', function($attribute, $value, $parameters, $validator) {
                return strtotime($value) !== false;
            }, 'The :attribute is not a valid time.');

            Validator::extend('valid_timezone', function ($attribute, $value, $parameters, $validator) {
                return in_array($value, timezone_identifiers_list());
            }, 'The :attribute is not a valid timezone.');

            $validator = Validator::make($request->except('_token'), [
                'name' => ['required', 'regex:/^[a-zA-Z0-9-_ ]+$/'],
                'alias' => ['required', 'regex:/^[a-zA-Z0-9-_ ]+$/'],
                'evaluation_type' => ['required', 'regex:/^[a-zA-Z0-9-_]+$/'],
                'daily_threshold' => ['required', 'numeric'],
                'daily_threshold_type' => ['required', 'regex:/^[a-zA-Z]+$/'],
                'max_drawdown' => ['required', 'numeric'],
                'max_drawdown_type' => ['required', 'regex:/^[a-zA-Z]+$/'],
                'phase_one_target_profit' => ['required', 'numeric'],
                'phase_one_target_profit_type' => ['required', 'regex:/^[a-zA-Z]+$/'],
                'phase_two_target_profit' => ['required', 'numeric'],
                'phase_two_target_profit_type' => ['required', 'regex:/^[a-zA-Z]+$/'],
                'consistency_rule' => ['numeric'],
                'consistency_rule_type' => ['regex:/^[a-zA-Z]+$/'],
                'reset_time' => 'valid_time',
                'reset_time_zone' => 'valid_timezone'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $funder = new Funder();
            $funder->user_id = auth()->id();
            $isCreated = $funder->save();

            if (!$isCreated) {
                return response()->json([
                    'errors' => __('Failed to create Funder.')
                ]);
            }

            $fundersMetaArr = [];

            foreach ($request->except(['_token']) as $key => $value) {
                $fundersMetaArr[] = [
                    'funder_id' => $funder->id,
                    'key' => strip_tags($key),
                    'value' => strip_tags($value)
                ];
            }

            FundersMetadata::insert($fundersMetaArr);

            return response()->json([
                'message' => __('Successfully created Funder.'),
                'funderId' => $funder->id
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'storeFunderError' => $e->getMessage()
            ], true));

            return response()->json([
               'errors' => __('An error occurred while creating the Funder.')
            ]);
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
        $funders = Funder::with('metadata')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->get();

        $fundersWithMetadata = $funders->map(function ($funder) {
            return [
                'id' => $funder->id,
                'user_id' => $funder->user_id,
                'created_at' => $funder->created_at,
                'updated_at' => $funder->updated_at,
                'metadata' => $funder->metadata->pluck('value', 'key')->toArray(),
            ];
        })->first();

        if (!$fundersWithMetadata) {
            return response()->json(['error' => __('Unable to find the funder record.')]);
        }

        return response()->json($fundersWithMetadata);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $data = [];

            foreach ($request->except(['_token']) as $key => $value) {
                $data[] = [
                    'funder_id' => $id,
                    'key' => strip_tags($key),
                    'value' => strip_tags($value)
                ];
            }

            $values = [];
            foreach ($data as $item) {
                $values[] = "({$item['funder_id']}, '{$item['key']}', '{$item['value']}')";
            }

            $valuesList = implode(', ', $values);

            $query = "INSERT INTO `funders_metadata` (`funder_id`, `key`, `value`) VALUES $valuesList
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

            $success = DB::statement($query);

            if (!$success) {
                return response()->json(['errors' => __('Failed to update the funder info.')]);
            }

            return response()->json(['message' => __('Successfully updated Funder info.')]);

        } catch (\Exception $e) {
            info(print_r([
                'funderUpdateError' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error updating the funder record.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $funder = Funder::find($id);

            if (!$funder) {
                return response()->json(['errors' => 'Failed to remove Funder.']);
            }

            $funder->delete();

            return response()->json([
                'message' => __('Successfully removed Funder.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'errorRemoveFunder' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error deleting the Funder.']);
        }

    }
}
