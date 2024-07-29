<?php

namespace App\Http\Controllers;

use App\Events\UnitsRequestReceived;
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
            ], [
                'name.unique' => __('The unit name already exist.'),
                'ip_address.unique' => __('The ip address is already added.')
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            $unit = new TradingUnitsModel();

            $unit->user_id = $userId;

            $unit->fill($request->only(['name', 'ip_address', 'status']));

            $unitSaved = $unit->save();

            if (!$unitSaved) {
                return response()->json([
                    'errors' => __('Unable to create the trading unit.')
                ]);
            }

            return response()->json([
                'unit_id' => $unit->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'errors' => __('Unable to create a unit.')
            ], 400);
        }
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
            $unitsArr[$unit->ip_address] = $unit->id;
        }

        return $unitsArr;
    }

    public function getTradingUnits()
    {
        $units = TradingUnitsModel::where('user_id', auth()->id())
            ->get()
            ->keyBy('id')
            ->map(function($item) {
                return collect($item)->except('user_id');
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
            ]);

            if (!$user) {
                return response()->json([
                    'error' => __('Failed to set unit login.')
                ]);
            }

            $user->createToken(env('UNIT_TOKEN_NAME'), ['unit'])->plainTextToken;

            UserUnitLogin::create([
                'user_id' => $currentUserId,
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
        $userId = Auth::id();

        return User::whereHas('unitUserLogin', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->first();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $unit = TradingUnitsModel::where('id', $id)->where('user_id', auth()->id())->first();

            if (!$unit) {
                return response()->json([
                    'errors' => __('Unit not found.')
                ]);
            }

            $unit->fill($request->only(['name', 'ip_address', 'status']));
            $unitSaved = $unit->update();

            if (!$unitSaved) {
                return response()->json([
                    'errors' => __('Failed to save the trading unit.')
                ]);
            }

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
        $unit = TradingUnitsModel::where('id', $id)->where('user_id', auth()->id())->first();

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
        event(new UnitsRequestReceived($request->get('message')));
    }
}
