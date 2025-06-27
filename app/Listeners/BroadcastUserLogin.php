<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use IlluminateAuthEventsLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastUserLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        broadcast(new UserLoggedIn($event->user));
    }
}
