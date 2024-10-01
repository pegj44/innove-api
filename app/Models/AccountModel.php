<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountModel extends Model
{
    use HasFactory;

    protected $table = 'accounts';
    protected $fillable = ['name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function owner()
    {
        return $this->hasOne(User::class)->where('is_owner', true);
    }

    public function units()
    {
        return $this->hasMany(TradingUnitsModel::class, 'account_id');
    }

    public function userAccounts()
    {
        return $this->hasMany(TradingIndividual::class, 'account_id');
    }

    public function funders()
    {
        return $this->hasMany(Funder::class, 'account_id');
    }
}
