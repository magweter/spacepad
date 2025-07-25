<?php

use App\Http\Middleware\CheckUserActive;
use App\Http\Middleware\CheckUserOnboarding;
use App\Http\Middleware\UpdateLastActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

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
