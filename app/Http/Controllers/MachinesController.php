<?php

namespace App\Http\Controllers;

use App\Models\MachineJobs;
use Illuminate\Http\Request;

class MachinesController extends Controller
{
    public function recordUsage(Request $request)
    {
        $machine = MachineJobs::where('user_id', auth()->id())
            ->where('ip', $request->get('ip'))
            ->where('machine_name', $request->get('machine_name'))
            ->first();

        if (!$machine) {
            $machine = new MachineJobs();

            $machine->user_id = auth()->id();
            $machine->machine_name = $request->get('machine_name');
            $machine->status = $request->get('status');
            $machine->ip = $request->get('ip');

            $machine->save();
        } else {
            $machine->status = $request->get('status');
            $machine->update();
        }

        return response()->json([
            'status' => 200,
            'updated' => 1
        ]);
    }

    public static function setMachineStatus($machine, $status)
    {
        $machine->status = $status;
        $machine->update();
    }

    public static function getAvailableMachine($userId = null, $ip = '')
    {
        $userId = (!empty($userId))? $userId : auth()->id();

        $machine = MachineJobs::where('user_id', $userId)
            ->where('status', 'idle')
            ->where('ip', $ip)
            ->first();

        if (!$machine) {
            return false;
        }

        return $machine;
    }

    public function getMachines(Request $request)
    {
        $machines = MachineJobs::where('user_id', auth()->id())->get();

        if (!$machines) {
            return response()->json(['status' => false]);
        }

        $userMachines = [];

        foreach ($machines as $machine) {
            $userMachines[] = [
                'machine_name' => $machine->machine_name,
                'status' => $machine->status
            ];
        }

        return response()->json($userMachines);
    }

    public function registerMachines(Request $request)
    {
        $machines = $request->get('machines');

        if (empty($machines)) {
            return response()->json(['status' => false]);
        }

        try {

            MachineJobs::where('user_id', auth()->id())->delete();

            $data = [];

            foreach ($machines as $machine) {
                if (stripos($machine, 'disruptor-trader-') !== false) {
                    $data[] = [
                        'user_id' => auth()->id(),
                        'machine_name' => $machine,
                        'status' => 'idle'
                    ];
                }
            }

            MachineJobs::insert($data);

            return response()->json(['status' => true]);

        } catch (\Exception $e) {
            return response()->json(['status' => false]);
        }
    }
}
