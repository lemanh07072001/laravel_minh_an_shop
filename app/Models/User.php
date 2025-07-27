<?php

namespace App\Models;

use App\Models\UserBan;
use App\Models\Profiles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    const STAUS_KEY = [
        "ACTIVE" => 0,
        "INACTIVE" => 1,
        "BAN" => 2,
    ];

    const ROLE_KEY = [
        'Admin' => 0,
        'User' => 1
    ];
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'status',
        'role'
    ];

    const UserBanKey = [
        '60'      => '1 giờ',
        '360'    => '6 giờ',
        '720'    => '12 giờ',
        '1440'   => '1 ngày',
        '4320'   => '3 ngày',
        '10080'  => '7 ngày',
        '43200'  => '30 ngày',
        '-1'     => 'Vĩnh viễn',
    ];

    const UserBanText = [
        '1 giờ'     => '60',
        '6 giờ'     => '360',
        '12 giờ'    => '720',
        '1 ngày'    => '1440',
        '3 ngày'    => '4320',
        '7 ngày'    => '10080',
        '10 ngày'   => '43200',
        'Vĩnh viễn' => '-1',
    ];

    public function bans()
    {
        return $this->hasOne(UserBan::class)->latestOfMany('created_at');
    }

    public function latestBan()
    {
        return $this->hasOne(UserBan::class)->latestOfMany();
    }

    public function profile()
    {
        return $this->hasOne(Profiles::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\CustomVerifyEmail);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
