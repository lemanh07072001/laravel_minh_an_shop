<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Profiles extends Model
{
    const Gender = [
        'male',
        'female',
        'other'
    ];

     protected $fillable = [
        'user_id',
        'avatar',
        'phone',
        'birthday',
        'gender',
        'bio',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
