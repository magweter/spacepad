<?php

namespace App\Http\Controllers\Portal;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\PortalShell;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workspace-wide settings that exist today: the name, starting another workspace, and the danger
 * zone (leaving, deleting an empty workspace). Per-display behaviour lives in Profiles.
 */
class SettingsPageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        $role = $workspace->getUserRole($user);
        $members = $workspace->members()->orderBy('name')->get(['users.id', 'users.name', 'users.email']);
        $isLastOwner = $role === WorkspaceRole::OWNER && $workspace->owners()->count() <= 1;
        $canDelete = $user->can('delete', $workspace);

        return Inertia::render('Settings/Index', [
            'shell' => PortalShell::for($request, $workspace, 'settings'),
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'members' => $members->count(),
            ],
            'can' => [
                'rename' => $user->can('update', $workspace),
                'delete' => $canDelete,
                // Leaving needs someone to leave behind; the only member deletes instead.
                'leave' => $members->count() > 1,
            ],
            'deleteBlocked' => ! $canDelete && $workspace->isOwnedBy($user) ? $this->deleteBlocked($workspace) : null,
            'leave' => [
                'needsSuccessor' => $isLastOwner,
                'candidates' => $isLastOwner
                    ? $members->where('id', '!=', $user->id)->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email])->values()->all()
                    : [],
            ],
            'urls' => [
                'update' => route('workspaces.update', $workspace),
                'destroy' => route('workspaces.destroy', $workspace),
                'store' => route('workspaces.store'),
                'leave' => route('workspaces.leave'),
                'team' => route('workspaces.members'),
                'profiles' => route('profiles.index'),
            ],
        ]);
    }

    /**
     * Why an owner cannot delete this workspace, mirroring Workspace::canBeDeletedBy().
     */
    private function deleteBlocked(Workspace $workspace): string
    {
        return match (true) {
            ! $workspace->isEmpty() => 'Only an empty workspace can be deleted. This one still has displays, boards or calendars.',
            $workspace->members()->count() > 1 => 'Only a workspace you are the only member of can be deleted.',
            $workspace->hasActiveSubscription() => 'A workspace with a subscription cannot be deleted. Cancel it on the billing page first.',
            default => 'You need another workspace you own or manage before you can delete this one.',
        };
    }
}
