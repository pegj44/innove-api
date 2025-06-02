<?php

namespace App\Http\Controllers;

use App\Models\UserSettingsModel;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function getSettings(Request $request)
    {
        $key = $request->get('key');

        if (!empty($key)) {
            $settings = UserSettingsModel::where('user_id', auth()->id())
                ->where('key', $key)
                ->first();

            $settings->value = maybe_unserialize($settings->value);
        } else {
            $settings = UserSettingsModel::where('user_id', auth()->id())
                ->get()
                ->mapWithKeys(function ($item) {
                    $item->value = maybe_unserialize($item->value);
                    return [$item->key => $item];
                })
                ->toArray();
        }

        if (empty($settings)) {
            return response()->json(null);
        }

        return response()->json($settings);
    }

    public function store(Request $request)
    {
        $key = $request->get('key');
        $value = $request->get('value');

        try {
            $settings = UserSettingsModel::where('user_id', auth()->id())
                ->where('key', $key)
                ->first();

            if (empty($settings)) {
                $setting = new UserSettingsModel();
                $setting->user_id = auth()->id();
                $setting->key = $key;
                $setting->value = maybe_serialize($value);
                $setting->save();
            } else {
                $this->update(new Request([
                    'key' => $key,
                    'value' => $value
                ]), $settings);
            }

            return response()->json([
                'message' => __('Settings saved.')
            ]);
        } catch (\Exception $e) {

            info(print_r([
                'ErrorStoreUserSettings' => $e->getMessage(),
                'data' => $request->all()
            ], true));

            return response()->json([
                'error' => __('Error saving the settings')
            ]);
        }
    }

    public function update(Request $request, $settings = null)
    {
        $key = $request->get('key');
        $value = $request->get('value');

        try {
            if (empty($settings)) {
                $settings = UserSettingsModel::where('user_id', auth()->id())
                    ->where('key', $key)
                    ->first();
            }

            $settings->user_id = auth()->id();
            $settings->key = $key;
            $settings->value = maybe_serialize($value);
            $settings->update();

            return response()->json([
                'message' => __('Settings updated.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'ErrorUpdateUserSettings' => $e->getMessage(),
                'data' => $request->all()
            ], true));

            return response()->json([
                'error' => __('Error updating the settings.')
            ]);
        }
    }

    public function destroy(string $id)
    {
        $settings = UserSettingsModel::where('user_id', auth()->id())
            ->where('id', $id)
            ->first();

        if (!empty($settings)) {
            $settings->delete();

            return response()->json([
                'message' => __('Successfully deleted settings.')
            ]);
        }

        return response()->json([
            'error' => __('Settings not found.')
        ]);
    }
}
