<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    /**
     * Show workspace management page
     */
    public function index()
    {
        $user = auth()->user();
        $workspaces = $user->accessibleWorkspaces();
        $primaryWorkspace = $user->primaryWorkspace();

        return view('pages.workspaces.index', [
            'workspaces' => $workspaces,
            'primaryWorkspace' => $primaryWorkspace,
        ]);
    }

    /**
     * Show workspace members page
     */
    public function show(Workspace $workspace)
    {
        $user = auth()->user();
        
        // Check if user has access to this workspace
        if (!$workspace->hasMember($user)) {
            abort(403, 'You do not have access to this workspace');
        }

        $workspace->load('members');

        return view('pages.workspaces.show', [
            'workspace' => $workspace,
            'userRole' => $workspace->getUserRole($user),
        ]);
    }

    /**
     * Add a member to the workspace
     */
    public function addMember(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = auth()->user();

        // Only workspace owners/admins can add members
        if (!$workspace->canBeManagedBy($user)) {
            abort(403, 'You do not have permission to add members to this workspace');
        }

        // Check if user has Pro (required for team features)
        if (!$user->hasPro()) {
            return back()->withErrors(['error' => 'Pro subscription is required to add team members']);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in([WorkspaceRole::ADMIN->value, WorkspaceRole::MEMBER->value])],
        ]);

        $memberUser = User::where('email', $validated['email'])->first();

        // Check if user is already a member
        if ($workspace->hasMember($memberUser)) {
            return back()->withErrors(['email' => 'User is already a member of this workspace']);
        }

        // Add member (use WorkspaceMember::create to generate ULID)
        $role = WorkspaceRole::from($validated['role']);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $memberUser->id,
            'role' => $role,
        ]);

        logger()->info('Workspace member added', [
            'workspace_id' => $workspace->id,
            'added_by' => $user->id,
            'member_id' => $memberUser->id,
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'Member added successfully');
    }

    /**
     * Update a member's role
     */
    public function updateMemberRole(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        $user = auth()->user();

        // Only workspace owners/admins can update roles
        if (!$workspace->canBeManagedBy($user)) {
            abort(403, 'You do not have permission to update member roles');
        }

        // Cannot change owner's role
        $memberRole = $workspace->getUserRole($member);
        if ($memberRole === WorkspaceRole::OWNER) {
            return back()->withErrors(['error' => 'Cannot change the owner\'s role']);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in([WorkspaceRole::ADMIN->value, WorkspaceRole::MEMBER->value])],
        ]);

        $role = WorkspaceRole::from($validated['role']);
        WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $member->id)
            ->update(['role' => $role]);

        logger()->info('Workspace member role updated', [
            'workspace_id' => $workspace->id,
            'updated_by' => $user->id,
            'member_id' => $member->id,
            'new_role' => $validated['role'],
        ]);

        return back()->with('success', 'Member role updated successfully');
    }

    /**
     * Remove a member from the workspace
     */
    public function removeMember(Workspace $workspace, User $member): RedirectResponse
    {
        $user = auth()->user();

        // Only workspace owners/admins can remove members
        if (!$workspace->canBeManagedBy($user)) {
            abort(403, 'You do not have permission to remove members from this workspace');
        }

        // Cannot remove owner
        $memberRole = $workspace->getUserRole($member);
        if ($memberRole === WorkspaceRole::OWNER) {
            return back()->withErrors(['error' => 'Cannot remove the workspace owner']);
        }

        $workspace->members()->detach($member->id);

        logger()->info('Workspace member removed', [
            'workspace_id' => $workspace->id,
            'removed_by' => $user->id,
            'member_id' => $member->id,
        ]);

        return back()->with('success', 'Member removed successfully');
    }

    /**
     * Update workspace name
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = auth()->user();

        // Only workspace owners/admins can update workspace
        if (!$workspace->canBeManagedBy($user)) {
            abort(403, 'You do not have permission to update this workspace');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace->update(['name' => $validated['name']]);

        logger()->info('Workspace updated', [
            'workspace_id' => $workspace->id,
            'updated_by' => $user->id,
            'new_name' => $validated['name'],
        ]);

        return back()->with('success', 'Workspace updated successfully');
    }
}

