<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/get-proxy', function () {

    return App\Helpers\ProxyHelper::fetchMultipleProxies();
});

