<?php

namespace App\Http\Controllers;

use App\Models\UserProfileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    public function getProfile(string $id)
    {

    }

    public function storeProfile(Request $request)
    {
        $existingProfile = UserProfileModel::where('user_id', $request->get('user_id'))->first();

        if (!empty($existingProfile)) {
            return response()->json(['error' => 'Profile already exists']);
        }

        $validator = Validator::make($request->only(['profile_image']), [
            'profile_image' => 'image|mimes:jpg,jpeg,png,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['validation_error' => $validator->errors()]);
        }

        $profile = new UserProfileModel();
        $profile->user_id = ($request->get('user_id'))? $request->get('user_id') : auth()->id();

        $data = collect($request->only([
            'first_name',
            'middle_name',
            'last_name',
            'company',
            'address',
            'country'
        ]))->filter(function ($value) {
            return !is_null($value);
        })->toArray();

        $profile->fill($data);

        $imageName = '';

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = $profile->user_id .'.'. $image->getClientOriginalExtension();
            $profile->profile_image = $imageName;
        }

        $create = $profile->save();

        if ($create && $request->hasFile('profile_image')) {
            $image->storeAs('public/images', $imageName);
        }

        return response()->json(['message' => __('Successfully updated user profile.')]);
    }

    public function updateProfile(Request $request, string $id)
    {

    }
}
