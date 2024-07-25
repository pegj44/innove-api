<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserEntityController extends Controller
{
    protected $entities = [
        'tradingIndividuals',
        'tradingIndividuals.metadata',
        'tradingIndividuals.tradingUnit',
        'funders',
        'funders.metadata',
        'accountCredentials',
        'accountCredentials.tradingIndividual',
        'accountCredentials.tradingIndividual.metadata',
        'units',
    ];

    public function getUserEntities(Request $request)
    {
        try {
            $withData = array_intersect($request->all(), $this->entities);

            if (empty($withData)) {
                return response()->json([]);
            }

            $data = User::with($withData)->where('id', auth()->id())->first();

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

//    public function getUserIndividualsAndFunders()
//    {
//        try {
//            $data = User::with([
//                'tradingIndividuals' => function ($query) {
//                    $query->with(['metadata' => function ($query) {
//                        $query->whereIn('key', ['first_name', 'middle_name', 'last_name']);
//                    }]);
//                },
//                'funders' => function ($query) {
//                    $query->with(['metadata' => function ($query) {
//                        $query->whereIn('key', ['name', 'alias']);
//                    }]);
//                }
//            ])->where('id', auth()->id())->first();
//
//            return response()->json($data);
//
//        } catch (\Exception $e) {
//            return response()->json([]);
//        }
//    }
}
