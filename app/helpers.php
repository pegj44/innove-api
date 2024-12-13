<?php

function getRemainingTargetProfit($item)
{
    $currentPhase = str_replace('phase-', '', $item['trading_account_credential']['current_phase']);
    $targetProfit = (float) $item['trading_account_credential']['phase_'. $currentPhase .'_total_target_profit'];
    $totalPnL = (float) $item['latest_equity'] - (float) $item['trading_account_credential']['starting_balance'];
    $remainingTP = $targetProfit - $totalPnL;
    $remainingTP = round($remainingTP, 2);

    return ($remainingTP <= 0 )? 200 : $remainingTP;
}


function getFunderAccountShortName($accountId)
{
    $toRemove = [
        'FTT-RALLY-',
        'Zero\d+k-s',
        'LV-Zero\d+k-s',
        'LV-\d+k-s',
        'ZeroDay50k-s',
        'ZeroDay100k-s',
        'ZeroDay150k-s',
    ];

    foreach ($toRemove as $pattern) {
        $regexPattern = '/^' . $pattern . '/';
        $accountId = preg_replace($regexPattern, '', $accountId);
    }

    return (strlen($accountId) > 7)? substr($accountId, 0, 7) .'...' : $accountId;
}

function getFunderAccountCredential($data)
{
    $data = (is_array($data))? $data : $data->toArray();
    $funderId = $data['trading_account_credential']['funder_id'];

    foreach ($data['trading_account_credential']['user_account']['funder_account_credential'] as $item) {
        if ($item['funder_id'] === $funderId) {
            return [
                'loginUsername' => $item['platform_login_username'],
                'loginPassword' => $item['platform_login_password']
            ];
        }
    }

    return false;
}

function getQueueUnitIdMachine($data, $index = 0, $type = 'unit')
{
    $items = explode(',', $data);
    $item = explode('|', $items[$index]);

    return ($type === 'unit')? $item[1] : $item[0];
}

function getQueueUnitId($units, $unit, $getPairedId = false, $type = 'unit')
{
    $items = explode(',', $units);
    $ids = [];

    foreach ($items as $item) {
        $itemArr = explode('|', $item);
        $ids[$itemArr[1]] = $itemArr[0];
    }

    if ($getPairedId) { // get the paired ID/Unit
        unset($ids[$unit]);

        if ($type === 'unit') {
            $id = array_keys($ids);
            return $id[0];
        }
        $id = array_values($ids);
        return $id[0];
    }

    if ($type === 'unit') {
        return $unit;
    }
    return $ids[$unit]; // get own ID/Unit
}

function getUnitAuthId()
{
    return \App\Models\UserUnitLogin::where('account_id', auth()->user()->account_id)->pluck('unit_user_id')->first();
}

function parseArgs($array, $default)
{
    if (!is_array($array)) {
        $array = [];
    }
    if (!is_array($default)) {
        $default = [];
    }

    $newArr = [];

    foreach ($array as $key => $value) {
        $newArr[$key] = $value;
        if ($value === null && isset($default[$key])) {
            $newArr[$key] = $default[$key];
        }
    }

    return array_merge($default, $newArr);
}

function getAuthUserId()
{
    if (auth()->user()->can('unit')) {
        $user = \App\Models\User::with('unitUserLogin')
            ->where('id', auth()->id())
            ->first();

        return $user->unitUserLogin->user_id;
    }

    return auth()->id();
}

function getAuthUser()
{
    if (auth()->user()->can('unit')) {
        $user = \App\Models\User::with('unitUserLogin')
            ->where('id', auth()->id())
            ->first();

        return \App\Models\User::find($user->unitUserLogin->user_id);
    }

    return auth()->user();
}

if (!function_exists('maybe_serialize')) {
    /**
     * Check if value is array, serialize if so.
     *
     * @param $value
     * @return mixed|string
     */
    function maybe_serialize($value)
    {
        return is_array($value) ? serialize($value) : $value;
    }
}

if (!function_exists('maybe_unserialize')) {
    /**
     * Check if value is serialized, un-serialize if so.
     *
     * @param $value
     * @return mixed
     */
    function maybe_unserialize($value)
    {
        if (is_array($value)) {
            return $value;
        }

        $unserialized = @unserialize($value);
        return $unserialized !== false || $value === 'b:0;' ? $unserialized : $value;
    }
}
