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
            ->where('account_id', auth()->user()->account_id)
            ->get();

        $fundersWithMetadata = $funders->map(function ($funder) {
            return [
                'id' => $funder->id,
                'account_id' => $funder->account_id,
                'name' => $funder->name,
                'alias' => $funder->alias,
                'reset_time' => $funder->reset_time,
                'reset_time_zone' => $funder->reset_time_zone,
                'created_at' => $funder->created_at,
                'updated_at' => $funder->updated_at,
                'metadata' => $funder->metadata->pluck('value', 'key')->toArray(),
            ];
        })->toArray();

        return response()->json($fundersWithMetadata);
    }

    public static function getByUrl($loginType, $url)
    {
        $funderMeta = FundersMetadata::where('key', $loginType)
            ->where('value', 'like', '%'. trim($url,'/') .'%')
            ->first();

        if (!$funderMeta) {
            return false;
        }

        return Funder::find($funderMeta->funder_id);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    private function validateInput($data)
    {
        Validator::extend('valid_time', function($attribute, $value, $parameters, $validator) {
            if (empty($value)) {
                return true;
            }
            return strtotime($value) !== false;
        }, 'The :attribute is not a valid time.');

        Validator::extend('valid_timezone', function ($attribute, $value, $parameters, $validator) {
            if (empty($value)) {
                return true;
            }
            return in_array($value, timezone_identifiers_list());
        }, 'The :attribute is not a valid timezone.');

        return Validator::make($data, [
            'name' => ['required', 'regex:/^[a-zA-Z0-9-_ ]+$/'],
            'alias' => ['required', 'regex:/^[a-zA-Z0-9-_ ]+$/'],
            'reset_time' => ['required', 'valid_time'],
            'reset_time_zone' => ['required', 'valid_timezone']
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $data = $request->except('_token');
            $validator = $this->validateInput($data);

            if ($validator->fails()) {
                return response()->json(['validation_error' => $validator->errors()], 422);
            }

            $funder = new Funder();
            $funder->account_id = auth()->user()->account_id;
            $funder->name = $data['name'];
            $funder->alias = $data['alias'];
            $funder->reset_time = $data['reset_time'];
            $funder->reset_time_zone = $data['reset_time_zone'];
            $funder->metaData = $data;

            $isCreated = $funder->save();

            if (!$isCreated) {
                return response()->json([
                    'errors' => __('Failed to create Funder.')
                ]);
            }

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
            ->where('account_id', auth()->user()->account_id)
            ->get();

        $fundersWithMetadata = $funders->map(function ($funder) {
            return [
                'id' => $funder->id,
                'account_id' => $funder->account_id,
                'name' => $funder->name,
                'alias' => $funder->alias,
                'reset_time' => $funder->reset_time,
                'reset_time_zone' => $funder->reset_time_zone,
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

            $data = $request->except(['_token']);
            $validator = self::validateInput($data);

            if ($validator->fails()) {
                return response()->json(['validation_error' => $validator->errors()], 422);
            }

            $funder = Funder::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            $funder->name = $data['name'];
            $funder->alias = $data['alias'];
            $funder->reset_time = $data['reset_time'];
            $funder->reset_time_zone = $data['reset_time_zone'];
            $funder->metaData = $data;

            $success = $funder->update();

            if (!$success) {
                return response()->json(['errors' => __('Failed to update the funder info.')]);
            }

            $funder->updateMetadata($funder);

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
            $funder = Funder::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

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
