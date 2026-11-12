<?php

namespace App\Http\Middleware;

use App\Enums\WorkspaceRole;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Props every Inertia page gets: who is signed in, the flash messages and what this instance
 * offers, and what the upgrade dialog needs.
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
            // What the upgrade dialog needs, on every page with a workspace: whether this person
            // can buy Pro, or whom they should ask.
            'upgrade' => fn () => $this->upgrade($request),
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

    /**
     * @return array<string, mixed>|null
     */
    private function upgrade(Request $request): ?array
    {
        $user = $request->user();
        $workspace = $user?->getSelectedWorkspace();

        if (! $workspace) {
            return null;
        }

        $selfHosted = (bool) config('settings.is_self_hosted');
        $canManage = $user->can('manageBilling', $workspace);

        return [
            'hasPro' => $workspace->hasPro(),
            'mustUpgradeForDisplay' => $user->shouldUpgradeForCurrentWorkspace(),
            'canManage' => $canManage,
            'selfHosted' => $selfHosted,
            // The first checkout carries the trial; a workspace that had a subscription before
            // gets the checkout without promising one.
            'trial' => ! $selfHosted && ! $workspace->subscriptions()->exists(),
            'managers' => $canManage ? [] : $workspace->members()
                ->wherePivotIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
                ->pluck('name')->map(fn ($name) => strtok($name, ' '))->values()->all(),
            'salesEmail' => config('settings.sales_email'),
            'urls' => [
                'checkout' => route('billing.checkout'),
                'billing' => route('billing.index'),
            ],
        ];
    }
}
