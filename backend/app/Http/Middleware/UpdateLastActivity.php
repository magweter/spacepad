<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            /** @var Device|User $user */
            $user = auth()->user();
            $user->updateLastActivity();
        }

        return $next($request);
    }
}
