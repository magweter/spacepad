<?php

use App\Http\Middleware\CheckUserActive;
use App\Http\Middleware\CheckUserOnboarding;
use App\Http\Middleware\UpdateLastActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;
use Spatie\GoogleTagManager\GoogleTagManagerMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        // On every web route, not just the authenticated ones. This middleware is what moves
        // data layer events across a redirect, and the first one of the funnel — sign_up —
        // is pushed by a guest: on the authenticated group only, it was dropped before it
        // ever reached the session. Where the scripts actually load is decided in the view,
        // by App\Services\FunnelTracking.
        $middleware->web(append: [GoogleTagManagerMiddleware::class]);

        $middleware->alias([
            'user.update-last-activity' => UpdateLastActivity::class,
            'user.active' => CheckUserActive::class,
            'user.onboarding' => CheckUserOnboarding::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'lemon-squeezy/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
