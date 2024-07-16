<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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

    public function userUnitLogins()
    {
        return $this->hasMany(UserUnitLogin::class, 'user_id');
    }

    public function unitUserLogins()
    {
        return $this->hasMany(UserUnitLogin::class, 'unit_user_id');
    }

    public function tradingIndividuals()
    {
        return $this->hasMany(TradingIndividual::class, 'user_id');
    }

    public function funders()
    {
        return $this->hasMany(Funder::class, 'user_id');
    }

    public function tradingCredentials()
    {
        return [];
    }
}
