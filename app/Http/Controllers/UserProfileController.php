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

        $image = $request->file('profile_image');
        $imageName = auth()->id() .'-'. auth()->user()->account_id .'.'. $image->getClientOriginalExtension();

        $profile->fill($request->only([
            'first_name',
            'middle_name',
            'last_name',
            'company',
            'address',
            'country'
        ]));

        $profile->profile_image = $imageName;
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
