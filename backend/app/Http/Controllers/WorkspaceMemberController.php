<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceJoinRequest;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceService;
use App\Support\PortalShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMemberController extends Controller
{
    public function __construct(protected WorkspaceService $workspaces) {}

    /**
     * The team page for the currently selected workspace: who is in it, who is invited and who
     * asked to join. Billing moved to its own page (Portal\BillingPageController) and the
     * workspace-wide actions (rename, leave, delete) to Settings.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();

        if (! $workspace) {
            abort(404, 'No workspace found');
        }

        $this->authorize('viewMembers', $workspace);

        $myRole = $workspace->getUserRole($user);
        $canChangeRoles = $user->can('updateMemberRole', $workspace);
        $canRemove = $user->can('removeMember', $workspace);
        $canRevoke = $user->can('revokeInvitation', $workspace);
        $canInvite = $user->can('invite', $workspace);

        $members = $workspace->members()->orderBy('name')->get()->map(function (User $member) use ($user) {
            $role = WorkspaceRole::fromPivot($member->pivot->role);

            return [
                'id' => $member->pivot->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $role->value,
                'roleLabel' => $role->label(),
                'isSelf' => $member->id === $user->id,
                'since' => $member->pivot->created_at?->format('j M Y'),
                'urls' => [
                    'update' => route('workspaces.members.update', $member->pivot->id),
                    'destroy' => route('workspaces.members.destroy', $member->pivot->id),
                ],
            ];
        })->values()->all();

        $invitations = $canRevoke
            ? $workspace->invitations()->pending()->with('invitedBy')->latest()->get()->map(fn (WorkspaceInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'roleLabel' => $invitation->role->label(),
                'invitedBy' => $invitation->invitedBy?->id === $user->id ? 'you' : $invitation->invitedBy?->name,
                'sent' => $invitation->created_at?->diffForHumans(),
                'expires' => $invitation->expires_at->diffForHumans(),
                'urls' => [
                    'resend' => route('workspaces.invitations.resend', $invitation),
                    'destroy' => route('workspaces.invitations.destroy', $invitation),
                ],
            ])->values()->all()
            : [];

        // Requests from colleagues who found this workspace through their email domain. Only
        // the people who can let them in see them.
        $joinRequests = $myRole?->canInvite()
            ? WorkspaceJoinRequest::where('workspace_id', $workspace->id)
                ->where('status', WorkspaceJoinRequest::PENDING)
                ->with('user')->latest()->get()
                ->map(fn (WorkspaceJoinRequest $request) => [
                    'id' => $request->id,
                    'name' => $request->user?->name,
                    'email' => $request->user?->email,
                    'askedAt' => $request->created_at?->diffForHumans(),
                    'url' => route('join-requests.show', $request),
                ])->values()->all()
            : [];

        return Inertia::render('Team/Index', [
            'shell' => PortalShell::for($request, $workspace, 'team'),
            'members' => $members,
            'invitations' => $invitations,
            'joinRequests' => $joinRequests,
            'invitationLifetimeDays' => WorkspaceInvitation::LIFETIME_DAYS,
            'can' => [
                'invite' => $canInvite,
                'revokeInvitation' => $canRevoke,
                'changeRoles' => $canChangeRoles,
                'removeMembers' => $canRemove,
                'manageBilling' => $user->can('manageBilling', $workspace),
            ],
            // Inviting colleagues is part of Pro.
            'needsPro' => ! $workspace->hasPro(),
            'roles' => collect(WorkspaceRole::cases())->map(fn (WorkspaceRole $r) => ['value' => $r->value, 'label' => $r->label()])->all(),
            'urls' => [
                'invite' => route('workspaces.invitations.store'),
                'billing' => route('billing.index'),
            ],
        ]);
    }

    /**
     * Change a member's role.
     */
    public function update(Request $request, WorkspaceMember $member): RedirectResponse
    {
        $workspace = $member->workspace;
        $this->authorize('updateMemberRole', $workspace);

        $validated = $request->validate([
            'role' => ['required', 'in:owner,admin,member'],
        ]);

        $user = auth()->user();

        if ($member->user_id === $user->id) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $newRole = WorkspaceRole::from($validated['role']);
        $currentRole = WorkspaceRole::fromPivot($member->role);

        // Demoting the last owner would leave the workspace with nobody who can manage
        // members or billing.
        if ($currentRole === WorkspaceRole::OWNER
            && $newRole !== WorkspaceRole::OWNER
            && $workspace->owners()->count() <= 1) {
            return back()->with('error', 'A workspace must always have at least one owner.');
        }

        $member->update(['role' => $newRole]);

        return back()->with('success', 'Role updated.');
    }

    /**
     * Remove a member from the workspace.
     *
     * Only the membership goes; everything they created belongs to the workspace and stays.
     */
    public function destroy(WorkspaceMember $member): RedirectResponse
    {
        $workspace = $member->workspace;
        $this->authorize('removeMember', $workspace);

        $user = auth()->user();

        if ($member->user_id === $user->id) {
            return back()->with('error', 'Use "Leave workspace" to remove yourself.');
        }

        if (WorkspaceRole::fromPivot($member->role) === WorkspaceRole::OWNER
            && $workspace->owners()->count() <= 1) {
            return back()->with('error', 'A workspace must always have at least one owner.');
        }

        $email = $member->user?->email;
        $member->delete();

        return back()->with('success', "Removed {$email} from this workspace.");
    }

    /**
     * Leave the workspace yourself.
     */
    public function leave(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $workspace = $user->getSelectedWorkspace();

        if (! $workspace) {
            abort(404, 'No workspace found');
        }

        $this->authorize('leave', $workspace);

        $isLastOwner = WorkspaceRole::fromPivot($workspace->getUserRole($user) ?? WorkspaceRole::MEMBER) === WorkspaceRole::OWNER
            && $workspace->owners()->count() <= 1;

        $otherMembers = $workspace->members()->where('users.id', '!=', $user->id)->exists();

        if ($isLastOwner && ! $otherMembers) {
            return back()->with('error', 'You are the only member. Delete the workspace instead.');
        }

        if ($isLastOwner) {
            $validated = $request->validate([
                'successor_user_id' => ['required', 'string'],
            ]);

            $successor = User::find($validated['successor_user_id']);

            if (! $successor || ! $workspace->hasMember($successor) || $successor->id === $user->id) {
                return back()->with('error', 'Choose a member of this workspace to take over ownership.');
            }

            $this->workspaces->transferOwnershipAwayFrom($workspace, $user, $successor);
        }

        WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->delete();

        session()->forget('selected_workspace_id');

        return redirect()->route('dashboard')->with('success', "You have left {$workspace->name}.");
    }
}
