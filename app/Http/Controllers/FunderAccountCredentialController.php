<?php

namespace App\Http\Controllers;

use App\Models\FunderAccountCredentialModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FunderAccountCredentialController extends Controller
{
    public function getFunderCredentialAccounts()
    {
        $items = FunderAccountCredentialModel::with('userAccount', 'funder')
            ->where('account_id', auth()->user()->account_id)
            ->get();

        return response()->json($items);
    }

    public function getFunderCredentialAccount(Request $request, $id)
    {
        $item = FunderAccountCredentialModel::where('account_id', auth()->user()->account_id)
            ->where('id', $id)
            ->first();

        return response()->json($item);
    }

    public function updateFunderCredentialAccount(Request $request, $id)
    {
        try {

            $validator = Validator::make($request->all(), [
                'trading_individual_id' => ['required', 'numeric'],
                'funder_id' => ['required', 'numeric'],
                'platform_login_username' => ['required'],
                'platform_login_password' => ['required']
            ]);

            if ($validator->fails()) {
                return response()->json(['validation_error' => $validator->errors()]);
            }

            $item = FunderAccountCredentialModel::where('account_id', auth()->user()->account_id)
                ->where('id', $id)
                ->first();

            $item->trading_individual_id = $request->get('trading_individual_id');
            $item->funder_id = $request->get('funder_id');
            $item->platform_login_username = $request->get('platform_login_username');

            if ($request->get('platform_login_password') !== '************') {
                $item->platform_login_password = $request->get('platform_login_password');
            }

            $item->update();

            return response()->json([
                'message' => __('Successfully updated credential')
            ]);
        } catch (\Exception $e) {

            info(print_r([
                'updateFunderAccountCredential' => $e->getMessage()
            ], true));

            return response()->json([
                'error' => __('Error updating the credential')
            ]);
        }
    }

    public function store(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'trading_individual_id' => ['required', 'numeric'],
                'funder_id' => ['required', 'numeric'],
                'platform_login_username' => ['required'],
                'platform_login_password' => ['required']
            ]);

            if ($validator->fails()) {
                return response()->json(['validation_error' => $validator->errors()]);
            }

            $credential = new FunderAccountCredentialModel();
            $credential->account_id = auth()->user()->account_id;
            $credential->trading_individual_id = $request->get('trading_individual_id');
            $credential->funder_id = $request->get('funder_id');
            $credential->platform_login_username = $request->get('platform_login_username');
            $credential->platform_login_password = $request->get('platform_login_password');
            $credential->save();

            return response()->json([
                'message' => __('Successfully added credential')
            ]);
        } catch (\Exception $e) {

            info(print_r([
                'storeFunderAccountCredential' => $e->getMessage()
            ], true));

            return response()->json([
                'error' => __('Error adding the credential')
            ]);
        }
    }

    public function destroyFunderCredentialAccount($id)
    {
        $item = FunderAccountCredentialModel::where('account_id', auth()->user()->account_id)
            ->where('id', $id)
            ->first();

        $item->delete();

        return response()->json([
            'message' => __('Successfully removed credential')
        ]);
    }
}
