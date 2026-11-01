<?php

use App\Http\Middleware\CheckUserActive;
use App\Http\Middleware\CheckUserOnboarding;
use App\Http\Middleware\RequireWebhookSigningSecret;
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
        // Which proxies may tell us the client's address through X-Forwarded-*. Every IP based
        // limiter (login, pairing, invitations) keys on that address, so trusting every source
        // lets a client that reaches the app directly pick its own address and sidestep them.
        // Behind Traefik from docker-compose.prod.yml the proxy is on the Docker network, so
        // TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16 covers it. The default stays
        // '*' for installs that put the app behind a proxy on another host.
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', '*'));
        $middleware->trustProxies(
            at: $trustedProxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trustedProxies))))
        );

        // On every web route, not just the authenticated ones. This middleware is what moves
        // data layer events across a redirect, and the first one of the funnel — sign_up —
        // is pushed by a guest: on the authenticated group only, it was dropped before it
        // ever reached the session. Where the scripts actually load is decided in the view,
        // by App\Services\FunnelTracking.
        $middleware->web(append: [GoogleTagManagerMiddleware::class]);

        // The billing webhook route is registered by the package without a middleware group,
        // so the guard goes on the global stack.
        $middleware->append(RequireWebhookSigningSecret::class);

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
