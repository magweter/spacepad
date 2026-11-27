<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserOnboarding
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // The wizard runs until it is finished or skipped, not until a calendar is connected:
        // connecting one is step four of seven.
        if (auth()->user()->hasFinishedOnboarding()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
