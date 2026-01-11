<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
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
        if (!$workspace) {
            abort(403, 'You do not have access to this workspace.');
        }
        
        // Store selected workspace in session
        // This persists during impersonation since we're using the impersonated user's session
        session()->put('selected_workspace_id', $workspace->id);
        
        logger()->info('User switched workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
            'is_impersonating' => session()->has('impersonating'),
        ]);
        
        return redirect()->route('dashboard')->with('success', "Switched to workspace: {$workspace->name}");
    }
}

