<?php

namespace App\Providers;

use Laravel\Reverb\Reverb;
use App\Events\UserLoggedIn;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event) {
            broadcast(new UserLoggedIn($event->user));
        });
    }
}
