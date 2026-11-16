<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\EmailDomain;

/**
 * The workspace a person's colleagues already use, found by the company domain of the owners.
 *
 * Only for company domains: two gmail.com addresses say nothing about working together.
 */
class ColleagueWorkspace
{
    public static function for(User $user): ?Workspace
    {
        if (config('settings.is_self_hosted') || ! EmailDomain::isCompany($user->email)) {
            return null;
        }

        $domain = EmailDomain::of($user->email);

        return Workspace::query()
            ->whereHas('members', fn ($query) => $query
                ->where('workspace_members.role', WorkspaceRole::OWNER->value)
                ->where('users.email', 'like', '%@'.$domain)
                ->whereKeyNot($user->id))
            ->whereDoesntHave('members', fn ($query) => $query->whereKey($user->id))
            ->withCount(['displays', 'members'])
            ->orderByDesc('displays_count')
            ->orderByDesc('members_count')
            ->oldest()
            ->first();
    }
}
