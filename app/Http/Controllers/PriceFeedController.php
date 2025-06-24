<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PriceFeedController extends Controller
{
    /**
     * Get the current XAUUSD price from the external API
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public static function getPrice($symbol = 'xauusd')
    {
        switch ($symbol) {
            case 'xauusd':
                return self::getXauUsdPrice();
        }

        return null;
    }
    public static function getXauUsdPrice()
    {
        try {
            // Make a request to the external API
            $response = Http::get('https://forex-data-feed.swissquote.com/public-quotes/bboquotes/instrument/XAU/USD');

            // Check if the request was successful
            if ($response->successful()) {
                $data = $response->json();

                // Check if data exists and has the expected structure
                if (!empty($data) && isset($data[0]['spreadProfilePrices'])) {
                    // Find the standard spread profile
                    $standardProfile = null;
                    foreach ($data[0]['spreadProfilePrices'] as $profile) {
                        if ($profile['spreadProfile'] === 'standard') {
                            $standardProfile = $profile;
                            break;
                        }
                    }

                    // If standard profile was found, return the ask price
                    if ($standardProfile && isset($standardProfile['ask'])) {
                        return $standardProfile['ask'];
                    }
                }

                return null;
            }

            return null;
        } catch (\Exception $e) {

            info(print_r([
                'getXauUsdPrice' => 'Error fetching XAUUSD price: ' . $e->getMessage()
            ], true));
            return null;
        }
    }
}
