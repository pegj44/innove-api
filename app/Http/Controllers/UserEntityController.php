<?php

namespace App\Http\Controllers;

use App\Models\AccountModel;
use Illuminate\Http\Request;

class UserEntityController extends Controller
{
    protected $entities = [
        'userAccounts',
        'userAccounts.metadata',
        'userAccounts.tradingUnit',
        'funders',
        'funders.metadata',
        'accountCredentials',
        'accountCredentials.tradingIndividual',
        'accountCredentials.tradingIndividual.metadata',
        'units',
    ];

    public function getAccountEntities(Request $request)
    {
        try {
            $withData = array_intersect($request->all(), $this->entities);

            if (empty($withData)) {
                return response()->json([]);
            }

            $data = AccountModel::with($withData)->where('id', auth()->user()->account_id)->first();

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([]);
        }
    }
}
