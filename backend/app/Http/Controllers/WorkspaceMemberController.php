<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function __construct(protected WorkspaceService $workspaces) {}

    /**
     * The team page for the currently selected workspace.
     */
    public function index(): View
    {
        $user = auth()->user();
        $workspace = $user->getSelectedWorkspace();

        if (! $workspace) {
            abort(404, 'No workspace found');
        }

        $this->authorize('viewMembers', $workspace);

        return view('pages.workspaces.members', [
            'workspace' => $workspace,
            'members' => $workspace->members()->orderBy('name')->get(),
            'invitations' => $workspace->invitations()->pending()->with('invitedBy')->latest()->get(),
            'myRole' => $workspace->getUserRole($user),
            'canInvite' => $user->hasProForWorkspace($workspace),
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
