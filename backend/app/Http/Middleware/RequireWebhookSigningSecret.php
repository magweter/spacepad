<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Lemon Squeezy package verifies webhook signatures only when a signing secret is
 * configured; without one it accepts any payload, and a forged subscription_created
 * hands the named workspace Pro. Fail closed: no secret, no webhook.
 */
class RequireWebhookSigningSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim((string) config('lemon-squeezy.path', 'lemon-squeezy'), '/');

        if ($request->is($path, $path.'/*') && blank(config('lemon-squeezy.signing_secret'))) {
            logger()->error('Lemon Squeezy webhook refused: LEMON_SQUEEZY_SIGNING_SECRET is not set', [
                'ip' => $request->ip(),
            ]);

            abort(503, 'Webhook signing is not configured.');
        }

        return $next($request);
    }
}
