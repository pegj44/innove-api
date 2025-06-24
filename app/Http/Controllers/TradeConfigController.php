<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TradeConfigController extends Controller
{
    /**
     * Get a value from the trade_config.json file using dot notation
     *
     * @param string $args Dot notation path to the value (e.g., "marginLimits.FPRO")
     * @return array The value at the specified path
     */
    public static function get($args = '')
    {
        // Read the JSON file
        $configPath = base_path('trade_config.json');
        $configContent = file_get_contents($configPath);
        $config = json_decode($configContent, true);

        // If no path is specified, return the entire config
        if (empty($args)) {
            return $config;
        }

        // Split the path by dots
        $keys = explode('.', $args);
        $value = $config;

        // Navigate through the nested structure
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return [];
            }
            $value = $value[$key];
        }

        return $value;
    }
}
