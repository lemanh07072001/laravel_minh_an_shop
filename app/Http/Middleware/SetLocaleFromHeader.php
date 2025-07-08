<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Lấy locale từ header "X-Locale" (mặc định là 'vi')
        $locale = $request->header('X-Locale', 'vi');

        // Nếu locale hợp lệ thì set
        if (in_array($locale, ['vi', 'en'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
