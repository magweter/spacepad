<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Brings someone back to the display setup after a detour through a calendar provider.
 *
 * The OAuth callbacks and the CalDAV form end on the dashboard. When they were started from
 * the setup, they return to the step that comes next instead.
 */
final class OnboardingReturn
{
    private const KEY = 'onboarding_return';

    public static function remember(Request $request): void
    {
        if ($request->boolean('onboarding')) {
            session([self::KEY => true]);
        } else {
            session()->forget(self::KEY);
        }
    }

    /**
     * The setup at the given step when the flow started there, otherwise the fallback route.
     */
    public static function redirect(string $step, string $fallbackRoute = 'dashboard'): RedirectResponse
    {
        if (session()->pull(self::KEY)) {
            return redirect()->route('setup.first-display', ['step' => $step]);
        }

        return redirect()->route($fallbackRoute);
    }
}
