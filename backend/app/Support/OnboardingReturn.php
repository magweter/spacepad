<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Brings someone back to the onboarding wizard after a detour through a calendar provider.
 *
 * The OAuth callbacks and the CalDAV form end on the dashboard. When they were started from
 * the wizard, they return to the step that comes next instead.
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
     * The wizard at the given step when the flow started there, otherwise the fallback route.
     */
    public static function redirect(string $step, string $fallbackRoute = 'dashboard'): RedirectResponse
    {
        if (session()->pull(self::KEY)) {
            return redirect()->route('onboarding', ['step' => $step]);
        }

        return redirect()->route($fallbackRoute);
    }
}
