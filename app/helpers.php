<?php

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
