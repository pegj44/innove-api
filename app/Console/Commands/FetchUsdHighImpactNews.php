<?php

namespace App\Console\Commands;

use App\Models\TradingNewsModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class FetchUsdHighImpactNews extends Command
{
    protected $signature = 'trade-report:fetch-usd-high-impact-news';
    protected $description = 'Get Trading News';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $response = Http::get('https://nfs.faireconomy.media/ff_calendar_thisweek.json');
            $data = $response->json();
            $filtered = collect($data)->filter(function ($item) {
                return isset($item['country'], $item['impact'])
                    && $item['country'] === 'USD'
                    && $item['impact'] === 'High';
            });

            $dates = [];

            TradingNewsModel::query()->delete();

            foreach ($filtered as $newsItem) {
                if (in_array($newsItem['date'], $dates)) { // skip duplicate date
                    continue;
                }
                $dates[] = $newsItem['date'];
                $eventDate = \Illuminate\Support\Carbon::parse($newsItem['date']);

                TradingNewsModel::create([
                    'country' => $newsItem['country'],
                    'impact'  => $newsItem['impact'],
                    'event_date'    => $eventDate,
                    'title'   => $newsItem['title'] ?? null,
                ]);
            }

            $this->info('USD high-impact news items fetched and stored successfully.');
        } catch (\Exception $e) {
            $this->error('Error fetching news data: ' . $e->getMessage());
        }

        return 0;
    }
}
