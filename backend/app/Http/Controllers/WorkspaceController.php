<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    /**
     * Upper bound on workspaces a single user may own.
     */
    private const MAX_OWNED_WORKSPACES = 10;

    /**
     * Switch to a different workspace
     *
     * Note: This works for all users (including non-Pro users) who are members of the workspace.
     * Workspace access is based on membership, not Pro status.
     * Also works during impersonation - uses the impersonated user's workspace memberships.
     */
    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'workspace_id' => 'required|string|exists:workspaces,id',
        ]);

        $user = Auth::user();
        $workspaceId = $request->input('workspace_id');

        // Validate user has access to this workspace (checks membership, not Pro status)
        // This works for both regular users and impersonated users
        $workspace = $user->workspaces()->find($workspaceId);
        if (! $workspace) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Store selected workspace in session
        // This persists during impersonation since we're using the impersonated user's session
        session()->put('selected_workspace_id', $workspace->id);

        logger()->info('User switched workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'is_impersonating' => session()->has('impersonating'),
        ]);

        return redirect()->route('dashboard')->with('success', "Switched to workspace: {$workspace->name}");
    }

    /**
     * Create an additional workspace, owned by the creator.
     *
     * Not Pro-gated — every account already gets one automatically — but capped so it
     * cannot be used to generate workspaces without limit.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($user->ownedWorkspaces()->count() >= self::MAX_OWNED_WORKSPACES) {
            return back()->with('error', 'You have reached the maximum number of workspaces. Please contact support if you need more.');
        }

        $workspace = DB::transaction(function () use ($validated, $user) {
            $workspace = Workspace::create(['name' => $validated['name']]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::OWNER,
            ]);

            return $workspace;
        });

        session()->put('selected_workspace_id', $workspace->id);

        return redirect()->route('dashboard')->with('success', "Workspace \"{$workspace->name}\" created.");
    }

    /**
     * Rename a workspace.
     *
     * Matters more than it looks: a shared workspace inherited from an auto-created
     * personal one is called "Someone's Workspace", which is the wrong name for a company.
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace->update(['name' => $validated['name']]);

        return back()->with('success', 'Workspace renamed.');
    }

    /**
     * Delete an empty workspace.
     *
     * Self-service tidy-up for the leftover personal workspace someone had before joining
     * their company's. Guarded by Workspace::canBeDeletedBy(), which requires it to be
     * empty, solely inhabited, unbilled, and that the user still has another workspace to
     * work in.
     */
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $validated = $request->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if ($validated['confirm_name'] !== $workspace->name) {
            return back()->withErrors(['confirm_name' => 'The workspace name does not match.']);
        }

        $name = $workspace->name;

        DB::transaction(function () use ($workspace) {
            WorkspaceMember::where('workspace_id', $workspace->id)->delete();
            $workspace->delete();
        });

        session()->forget('selected_workspace_id');

        logger()->info('Empty workspace deleted by owner', [
            'workspace_id' => $workspace->id,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('dashboard')->with('success', "Workspace \"{$name}\" deleted.");
    }
}
