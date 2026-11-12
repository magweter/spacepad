<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Props every Inertia page gets: who is signed in, the flash messages and what this instance
 * offers. Only the sign-in and onboarding pages are Inertia so far; the rest is Blade.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success') ?? $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'app' => [
                'selfHosted' => (bool) config('settings.is_self_hosted'),
                'recaptchaSiteKey' => config('recaptchav3.sitekey') ?: null,
                'providers' => [
                    'microsoft' => (bool) config('services.microsoft.enabled'),
                    'google' => (bool) config('services.google.enabled'),
                    'caldav' => (bool) config('services.caldav.enabled'),
                ],
                'emailLogin' => ! config('settings.disable_email_login'),
            ],
        ];
    }
}
