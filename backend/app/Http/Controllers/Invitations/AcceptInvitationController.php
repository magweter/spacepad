<?php

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceInvitationService;
use App\Services\WorkspaceTransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Accepting a workspace invitation.
 *
 * Kept out of the authenticated route group on purpose. The invitation link is itself the
 * authentication: possession of the token proves control of the mailbox, the same trust
 * level as the magic login link this app already uses. Routing invitees through /login
 * instead would auto-create an account for the unknown address (LoginController does that)
 * plus a personal workspace — recreating the very problem shared workspaces solve.
 */
class AcceptInvitationController extends Controller
{
    public function __construct(
        protected WorkspaceInvitationService $invitations,
        protected WorkspaceTransferService $transfers,
    ) {}

    /**
     * Show the invitation, or explain why it cannot be used.
     */
    public function show(string $token): View|RedirectResponse|Response
    {
        $invitation = $this->invitations->findByPlainToken($token);

        if (! $invitation) {
            return response()->view('pages.invitations.invalid', [], 404);
        }

        $invitation->load(['workspace', 'invitedBy']);
        $user = Auth::user();

        if ($invitation->accepted_at !== null) {
            // Already used. If the visitor is the member it created, just send them in.
            if ($user && $invitation->workspace->hasMember($user)) {
                session()->put('selected_workspace_id', $invitation->workspace_id);

                return redirect()->route('dashboard')
                    ->with('info', "You are already a member of {$invitation->workspace->name}.");
            }

            return response()->view('pages.invitations.invalid', [], 404);
        }

        if ($invitation->isExpired()) {
            return response()->view('pages.invitations.expired', [
                'invitation' => $invitation,
            ], 410);
        }

        if (! User::isAllowedLogin($invitation->email)) {
            return response()->view('pages.invitations.blocked', [
                'invitation' => $invitation,
            ], 403);
        }

        // Signed in as somebody else: never offer to accept into the current account, that
        // is exactly the confusion this feature removes.
        if ($user && strcasecmp($user->email, $invitation->email) !== 0) {
            return view('pages.invitations.mismatch', [
                'invitation' => $invitation,
                'token' => $token,
            ]);
        }

        return view('pages.invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'movableWorkspaces' => $user ? $this->movableWorkspaces($user, $invitation->workspace_id) : collect(),
        ]);
    }

    /**
     * Workspaces the accepting user owns whose contents could come along.
     *
     * Only offered when there is actually something to move — an empty personal workspace
     * needs no decision from the user.
     *
     * @return Collection<int, array{workspace: Workspace, counts: array<string, int>}>
     */
    private function movableWorkspaces(User $user, string $targetWorkspaceId)
    {
        return $user->ownedWorkspaces()->get()
            ->reject(fn ($workspace) => $workspace->id === $targetWorkspaceId)
            ->map(fn ($workspace) => [
                'workspace' => $workspace,
                'counts' => $this->transfers->preview($workspace),
            ])
            ->filter(fn ($entry) => $entry['counts'] !== [])
            ->values();
    }

    /**
     * Join the workspace.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findByPlainToken($token);

        abort_if($invitation === null, 404);
        abort_unless($invitation->isPending(), 410, 'This invitation is no longer valid.');
        abort_unless(User::isAllowedLogin($invitation->email), 403);

        $user = Auth::user();

        if (! $user) {
            $user = User::whereRaw('lower(email) = ?', [strtolower($invitation->email)])->first()
                ?? User::createForInvitation($invitation);

            // Log in outside the accept transaction so a session write cannot roll back.
            Auth::login($user);
            $request->session()->regenerate();
        }

        abort_unless(strcasecmp($user->email, $invitation->email) === 0, 403);

        $this->invitations->accept($invitation, $user);

        session()->put('selected_workspace_id', $invitation->workspace_id);

        $message = "You've joined {$invitation->workspace->name}.";

        // Optionally bring the invitee's own displays, boards and calendar accounts along.
        // Resolved from their owned workspaces, so a hostile source id cannot be injected.
        if ($request->boolean('move_data')) {
            $source = $user->ownedWorkspaces()->find($request->input('source_workspace_id'));

            if ($source && $source->id !== $invitation->workspace_id) {
                $this->transfers->move(
                    $source,
                    $invitation->workspace,
                    $user,
                    $request->boolean('adopt_orphans'),
                );

                $message .= " Your data from \"{$source->name}\" has been moved across — that workspace is now empty and can be deleted from the Team page.";
            }
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    /**
     * Sign out and come straight back to this invitation.
     *
     * The generic logout route redirects to intended('/') and invalidates the session on the
     * way, so it cannot carry us back here — hence this small dedicated action.
     */
    public function switchAccount(Request $request, string $token): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('invitations.show', $token);
    }

    /**
     * Decline an invitation.
     */
    public function decline(string $token): RedirectResponse
    {
        $invitation = $this->invitations->findByPlainToken($token);

        abort_if($invitation === null, 404);

        if ($invitation->isPending()) {
            $invitation->delete();
        }

        return redirect()->route('login')->with('info', 'Invitation declined.');
    }
}
