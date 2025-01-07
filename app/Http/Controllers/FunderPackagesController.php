<?php

namespace App\Http\Controllers;

use App\Models\FunderPackagesModel;
use Illuminate\Http\Request;

class FunderPackagesController extends Controller
{
    public function getPackage(string $id)
    {
        $package = FunderPackagesModel::where('account_id', auth()->user()->account_id)
            ->where('id', $id)
            ->first();

        return response()->json($package);
    }

    public function update(Request $request, string $id)
    {
        try {
            $package = FunderPackagesModel::where('account_id', auth()->user()->account_id)
                ->where('id', $id)
                ->first();

            if (empty($package)) {
                return response()->json([
                    'errors' => __('Package not found.')
                ]);
            }

            $package->fill($request->all());
            $update = $package->save();

            if (!$update) {
                return response()->json(['errors' => __('Failed to update package.')]);
            }

            return response()->json(['message' => __('Successfully updated package.')]);
        } catch (\Exception $err) {
            info(print_r([
                'updateFunderPackage' => $err->getMessage()
            ], true));
            return response()->json(['errors' => __('Error updating the package.')]);
        }
    }

    public function store(Request $request)
    {
        try {
            $package = new FunderPackagesModel();

            $data = $request->all();
            $data['account_id'] = auth()->user()->account_id;

            $package->fill($data);
            $package->save();
            $createdId = $package->id;

            return response([
                'data' => $createdId,
                'message' => __('Successfully created funder package')
            ]);
        } catch (\Exception $err) {

            info(print_r([
                'storeFunderPackage' => $err->getMessage()
            ], true));

            return response([
                'errors' => __('Failed to create funder package'),
            ]);
        }
    }

    public function packages()
    {
        $packages = FunderPackagesModel::with('funder')
            ->where('account_id', auth()->user()->account_id)
            ->get();

        return response()->json($packages);
    }

    public function destroy(string $id)
    {
        try {
            $funder = FunderPackagesModel::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            if (!$funder) {
                return response()->json(['errors' => 'Failed to remove funder package.']);
            }

            $funder->delete();

            return response()->json([
                'message' => __('Successfully removed funder package.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'errorRemoveFunderPackage' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error deleting the funder package.']);
        }
    }
}
