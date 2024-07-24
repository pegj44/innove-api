<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundersMetadata extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $casts = [
        'value' => 'array'
    ];

    protected $fillable = [
        'funder_id',
        'key',
        'value'
    ];

    public static $defaultMetadata = [
        'evaluation_type',
        'daily_threshold',
        'daily_threshold_type',
        'max_drawdown',
        'max_drawdown_type',
        'phase_one_target_profit',
        'phase_one_target_profit_type',
        'phase_two_target_profit',
        'phase_two_target_profit_type',
        'consistency_rule',
        'consistency_rule_type',
        'reset_time',
        'reset_time_zone',
    ];

    public function funder()
    {
        return $this->belongsTo(Funder::class);
    }
}
