<?php

namespace App\Support;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * What the portal shell around an Inertia page shows: the workspace and its switcher, the
 * navigation and who is signed in. Pages that are not rebuilt yet are linked as they are.
 */
class PortalShell
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Request $request, Workspace $workspace, string $active): array
    {
        $user = $request->user();
        $role = $workspace->members()->whereKey($user->id)->first()?->pivot?->role;
        $hasPro = $workspace->hasPro();

        $nav = [
            ['key' => 'overview', 'label' => 'Overview', 'icon' => 'overview', 'href' => route('dashboard')],
            // 'new' shows a green label in the sidebar while the page is new.
            ['key' => 'live', 'label' => 'Live view', 'icon' => 'live', 'href' => route('live.index'), 'new' => true],
            ['key' => 'displays', 'label' => 'Displays', 'icon' => 'displays', 'href' => route('displays.index'), 'count' => $workspace->displays()->count()],
            ['key' => 'boards', 'label' => 'Boards', 'icon' => 'boards', 'href' => route('boards.index'), 'pro' => ! $hasPro],
            ['key' => 'profiles', 'label' => 'Profiles', 'icon' => 'profiles', 'href' => route('profiles.index'), 'pro' => ! $hasPro],
            ['key' => 'calendars', 'label' => 'Calendars', 'icon' => 'calendars', 'href' => route('calendars.index')],
            ['key' => 'team', 'label' => 'Team', 'icon' => 'team', 'href' => route('workspaces.members'), 'divider' => true],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => 'billing', 'href' => route('billing.index')],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'href' => route('settings.index')],
        ];

        // The admin panel, for Spacepad admins only and never while impersonating someone (the
        // admin controllers refuse that too, see ChecksAdminAccess).
        if ($user->isAdmin() && ! $request->session()->get('impersonating') && ! config('settings.is_self_hosted')) {
            $nav[] = ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'href' => route('admin.index'), 'divider' => true];
        }

        return [
            'workspace' => $workspace->name,
            'plan' => $hasPro ? 'Pro workspace' : 'Free workspace',
            'user' => $user->name,
            'role' => $role ? WorkspaceRole::fromPivot($role)->label() : null,
            'nav' => array_map(fn ($item) => $item + ['active' => $item['key'] === $active], $nav),
            'workspaces' => $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name'])
                ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'current' => $w->id === $workspace->id])->values()->all(),
            'switchUrl' => route('workspaces.switch'),
            'logout' => route('logout'),
            'account' => route('profile.show'),
            'accountActive' => $active === 'account',
        ];
    }
}
