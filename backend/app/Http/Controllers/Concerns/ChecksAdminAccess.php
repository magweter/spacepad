<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Guard for the admin panel.
 *
 * Admin access is not a policy or a route middleware here, it is checked at the top of
 * every action. This trait is the single implementation; AdminController and
 * AdminRoadmapController each used to carry their own copy.
 */
trait ChecksAdminAccess
{
    protected function checkAdminAccess(): void
    {
        // Impersonating an account must never grant admin powers over other accounts.
        if (session()->get('impersonating')) {
            abort(403, 'Cannot access admin panel while impersonating. Please stop impersonating first.');
        }

        $user = Auth::user();

        if (! $user || ! $user->isAdmin() || config('settings.is_self_hosted')) {
            abort(403);
        }
    }
}
