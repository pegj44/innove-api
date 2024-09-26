<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_id',
        'is_owner'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function account()
    {
        return $this->belongsTo(AccountModel::class);
    }

    public function isOwner()
    {
        return $this->is_owner;
    }

//    public function units()
//    {
//        return $this->hasMany(TradingUnitsModel::class);
//    }

//    public function userUnitLogins()
//    {
//        return $this->hasMany(UserUnitLogin::class, 'user_id');
//    }
//
    public function unitUserLogin()
    {
        return $this->hasOne(UserUnitLogin::class, 'unit_user_id');
    }
//
//    public function tradingIndividuals()
//    {
//        return $this->hasMany(TradingIndividual::class, 'user_id');
//    }
//
//    public function funders()
//    {
//        return $this->hasMany(Funder::class, 'user_id');
//    }
//
//    public function accountCredentials()
//    {
//        return $this->hasMany(TradingAccountCredential::class);
//    }
//
//    public function tradeReports()
//    {
//        return $this->hasMany(TradeReport::class);
//    }
//
//    public function pairedItems()
//    {
//        return $this->hasMany(PairedItems::class);
//    }
}
