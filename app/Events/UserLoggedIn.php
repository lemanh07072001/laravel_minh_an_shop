<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedIn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('login-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.loggedin';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => "Tài khoản {$this->user->email} đã đăng nhập.",
            'user_id' => $this->user->id,
        ];
    }
}
