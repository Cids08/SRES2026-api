<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Prevent Laravel from redirecting API auth failures to /login
        Authenticate::redirectUsing(function (Request $request) {
            return null;
        });

        // Contact form — max 5 messages per IP per hour
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perHour(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many messages sent. Please wait before trying again.',
                    ], 429);
                });
        });

        // Enroll form — max 3 submissions per IP per hour
        RateLimiter::for('enroll', function (Request $request) {
            return Limit::perHour(3)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many enrollment submissions. Please wait before trying again.',
                    ], 429);
                });
        });
    }
}