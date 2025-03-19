<?php

namespace App\Http\Controllers;

use App\Events\UnitsEvent;
use App\Events\UnitsRequestReceived;
use App\Events\WebPush;
use App\Models\TradingUnitsModel;
use App\Models\User;
use App\Models\UserUnitLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TradingUnitsController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
//        try {
            $accountId = auth()->user()->account_id;

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('trading_units', 'name')->where(function ($query) use ($accountId) {
                        return $query->where('account_id', $accountId);
                    })
                ]
            ], [
                'name.unique' => __('The unit name already exist.'),
                'unit_id.unique' => __('The unit ID is already added.')
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $unit = new TradingUnitsModel();

            $unit->account_id = $accountId;

            $unit->fill($request->only(['name', 'unit_id', 'status']));

            $unitSaved = $unit->save();

            if (!$unitSaved) {
                return response()->json([
                    'errors' => __('Unable to create the trading unit.')
                ]);
            }

            return response()->json([
                'unit_id' => $unit->id
            ]);

//        } catch (\Exception $e) {
//            return response()->json([
//                'errors' => __('Unable to create a unit.')
//            ], 400);
//        }
    }

    public static function getUnitsArray()
    {
        $unitsObj = new TradingUnitsController();
        $units = $unitsObj->getTradingUnits();
        $units = $units->getData();

        if (empty($units)) {
            return [];
        }

        $unitsArr = [];

        foreach ($units as $unit) {
            $unitsArr[$unit->unit_id] = $unit->id;
        }

        return $unitsArr;
    }

    public function getTradingUnits()
    {
        $units = TradingUnitsModel::where('account_id', auth()->user()->account_id)
            ->get()
            ->keyBy('id')
            ->map(function($item) {
                return collect($item)->except('account_id');
            })
            ->toArray();

        return response()->json($units);
    }

    public function updatePassword(Request $request)
    {
        try {
            $currentUserId = auth()->id();
            $unitLogin = $this->getLoginDetails();

            if (empty($unitLogin)) {
                return response()->json(['errors' => __('Error updating the unit login')]);
            }

            $validationArgs = [
                'username' => [
                    'required',
                    'regex:/^[a-zA-Z0-9-_ ]+$/',
                    Rule::unique('users', 'email')->ignore($currentUserId),
                ]
            ];

            $pwData = array_filter($request->only(['password', 'password_confirmation']));
            $data = ['username' => $request->get('username')];

            if (!empty($pwData)) {
                $data = array_merge($data, $pwData);
                $validationArgs['password'] = [
                    'required',
                    'confirmed',
                    'min:6'
                ];
            }

            $validator = Validator::make($data, $validationArgs);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $unitLogin->name = $request->get('username');
            $unitLogin->email = $data['username'] .'@innovetechsolutions.rpahandler';
            if (!empty($pwData)) {
                $unitLogin->password = Hash::make($request->get('password'));
            }
            $isUpdated = $unitLogin->update();

            if (!$isUpdated) {
                return response()->json(['errors' => __('Error updating the unit login')]);
            }

            return response()->json(['message' => __('Successfully updated unit login')]);

        } catch (\Exception $e) {
            info(print_r([
                'updateUnitPwError' => $e->getMessage()
            ], true));
            return response()->json([
                'errors' => __('Failed to update unit login.')
            ]);
        }
    }

    public function setPassword(Request $request)
    {
        try {
            $currentUserId = auth()->id();
            $data = $request->only(['username', 'password', 'password_confirmation']);
            $email = $request->get('username') .'@innovetechsolutions.rpahandler';
            $data['email'] = $email;

            $validator = Validator::make($data, [
                'username' => [
                    'required',
                    'regex:/^[a-zA-Z0-9-_]+$/',
                    Rule::unique('users', 'email')->ignore($currentUserId),
                ],
                'password' => [
                    'required',
                    'confirmed',
                    'min:6'
                ],
            ], [
                'username.regex' => __('The username must not contain special characters. Only "-" and "_" are allowed.'),
                'password.min' => __('Password should be at least 6 characters long.')
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = User::create([
                'name' => $request->get('username'),
                'email' => $email,
                'password' => Hash::make($request->get('password')),
                'account_id' => auth()->user()->account_id,
                'is_owner' => false
            ]);

            if (!$user) {
                return response()->json([
                    'error' => __('Failed to set unit login.')
                ]);
            }

            $user->assignRole('unit');
            $user->createToken(env('UNIT_TOKEN_NAME'), ['unit'])->plainTextToken;

            UserUnitLogin::create([
                'account_id' => auth()->user()->account_id,
                'unit_user_id' => $user->id
            ]);

            return response()->json([
                'message' => __('Successfully saved unit login.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'setUnitPwError' => $e->getMessage()
            ], true));
            return response()->json([
                'errors' => __('Failed to set unit login.')
            ]);
        }
    }

    public function getSettings()
    {
        return [
            'login' => $this->getLoginDetails()
        ];
    }

    public function getLoginDetails()
    {
        $userId = auth()->user()->account_id;

        return User::whereHas('unitUserLogin', function ($query) use ($userId) {
            $query->where('account_id', $userId);
        })->first();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $unit = TradingUnitsModel::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            if (!$unit) {
                return response()->json([
                    'errors' => __('Unit not found.')
                ]);
            }

            $unit->fill($request->only(['name', 'unit_id', 'status']));
            $unitSaved = $unit->update();

            if (!$unitSaved) {
                return response()->json([
                    'errors' => __('Failed to save the trading unit.')
                ]);
            }

            WebPush::dispatch(auth()->user()->account_id, [], 'unit-updated');

            return response()->json($this->getTradingUnits());
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
        $unit = TradingUnitsModel::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

        if (!$unit) {
            return response()->json([
               'errors' => __('Unit does not exists.')
            ]);
        }

        $unit->delete();

        return response()->json([
           'message' => __('Successfully deleted unit.')
        ]);
    }

    public function testBroadcastConnection(Request $request)
    {
        UnitsEvent::dispatch(9, '100.22.3', 'testcommand2');
    }
}
