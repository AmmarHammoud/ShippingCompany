<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->lang;
        if (!$locale) {
            $locale = session('locale', config('app.locale'));
        }
        if (!in_array($locale, ['en', 'ar'])) {
            $locale = 'en';
        }
        App::setLocale($locale);
        return $next($request);
    }
}
