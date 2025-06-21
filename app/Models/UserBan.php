<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserBan extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

     protected $fillable = ['user_id', 'reason', 'banned_at', 'unbanned_at', 'banned_by','lock_time'];
}
