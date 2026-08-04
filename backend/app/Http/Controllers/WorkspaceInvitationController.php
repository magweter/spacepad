<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceInvitationController extends Controller
{
    public function __construct(protected WorkspaceInvitationService $invitations) {}

    /**
     * Invite a colleague to the currently selected workspace.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $workspace = $user->getSelectedWorkspace();

        if (! $workspace) {
            abort(404, 'No workspace found');
        }

        if (! $user->hasProForWorkspace($workspace)) {
            abort(403, 'Team collaboration is a Pro feature. Please upgrade to access this feature.');
        }

        $this->authorize('invite', $workspace);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            // Ownership is never handed out by invitation.
            'role' => ['required', 'in:admin,member'],
        ]);

        $invitation = $this->invitations->invite(
            $workspace,
            $validated['email'],
            WorkspaceRole::from($validated['role']),
            $user,
        );

        return back()->with('success', "Invitation sent to {$invitation->email}.");
    }

    /**
     * Send a fresh link for a pending invitation.
     */
    public function resend(WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->authorize('invite', $invitation->workspace);

        $this->invitations->resend($invitation, auth()->user());

        return back()->with('success', "Invitation re-sent to {$invitation->email}. The previous link no longer works.");
    }

    /**
     * Withdraw a pending invitation.
     */
    public function destroy(WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->authorize('revokeInvitation', $invitation->workspace);

        $email = $invitation->email;
        $invitation->delete();

        return back()->with('success', "Invitation for {$email} withdrawn.");
    }
}
