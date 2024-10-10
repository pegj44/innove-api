<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunderAccountCredentialModel extends Model
{
    use HasFactory;

    protected $table = 'funder_account_credentials';

    public function account()
    {
        return $this->belongsTo(AccountModel::class, 'account_id');
    }

    public function userAccount()
    {
        return $this->belongsTo(TradingIndividual::class, 'trading_individual_id');
    }

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }
}
