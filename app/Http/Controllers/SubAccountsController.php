<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserUnitLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubAccountsController extends Controller
{
    public function store(Request $request)
    {
        $currentUserId = auth()->id();
        $data = $request->only(['name', 'email', 'password', 'password_confirmation']);

        $validator = Validator::make($data, [
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($currentUserId),
            ],
            'password' => [
                'required',
                'confirmed',
                'min:6'
            ],
        ], [
            'email.email' => __('Please provide a valid email address.'),
            'password.min' => __('Password should be at least 6 characters long.')
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($request->get('password')),
            'account_id' => auth()->user()->account_id,
            'is_owner' => false
        ]);

        if (!$user) {
            return response()->json([
                'error' => __('Failed to create sub-account.')
            ]);
        }

        $user->assignRole('investor');
        $user->createToken('innove-investor-api', ['investor'])->plainTextToken;

        return response()->json([
            'message' => __('Successfully created sub-account.')
        ]);
    }

    public function getSubAccounts()
    {

    }

    public function update(Request $request, string $id)
    {

    }

    public function destroy(string $id)
    {

    }

    public function edit(string $id)
    {

    }
}
