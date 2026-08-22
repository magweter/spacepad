<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceInvitationService
{
    /**
     * Invite an address to a workspace, or re-send an existing pending invitation.
     *
     * Re-inviting rotates the token, which invalidates any previously emailed link.
     *
     * @throws ValidationException
     */
    public function invite(Workspace $workspace, string $email, WorkspaceRole $role, User $inviter): WorkspaceInvitation
    {
        $email = strtolower(trim($email));

        if ($role === WorkspaceRole::OWNER) {
            throw ValidationException::withMessages([
                'role' => 'Ownership cannot be handed out by invitation.',
            ]);
        }

        if (strcasecmp($email, $inviter->email) === 0) {
            throw ValidationException::withMessages([
                'email' => 'You are already a member of this workspace.',
            ]);
        }

        if (! User::isAllowedLogin($email)) {
            throw ValidationException::withMessages([
                'email' => 'That organization or email address is not allowed to sign in.',
            ]);
        }

        $existing = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($existing && $workspace->hasMember($existing)) {
            throw ValidationException::withMessages([
                'email' => "{$email} is already a member of this workspace.",
            ]);
        }

        $plainToken = Str::random(64);

        $invitation = WorkspaceInvitation::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'email' => $email,
            ],
            [
                'invited_by_user_id' => $inviter->id,
                'role' => $role,
                'token' => WorkspaceInvitation::hashToken($plainToken),
                'expires_at' => now()->addDays(WorkspaceInvitation::LIFETIME_DAYS),
                'accepted_at' => null,
                'accepted_by_user_id' => null,
            ]
        );

        $invitation->plainToken = $plainToken;

        $this->send($invitation, $inviter);

        return $invitation;
    }

    /**
     * Re-send an invitation with a fresh token and expiry.
     */
    public function resend(WorkspaceInvitation $invitation, User $inviter): WorkspaceInvitation
    {
        $plainToken = Str::random(64);

        $invitation->update([
            'token' => WorkspaceInvitation::hashToken($plainToken),
            'expires_at' => now()->addDays(WorkspaceInvitation::LIFETIME_DAYS),
            'invited_by_user_id' => $inviter->id,
        ]);

        $invitation->plainToken = $plainToken;

        $this->send($invitation, $inviter);

        return $invitation;
    }

    /**
     * Look an invitation up by the token from the emailed URL.
     */
    public function findByPlainToken(string $plainToken): ?WorkspaceInvitation
    {
        return WorkspaceInvitation::where('token', WorkspaceInvitation::hashToken($plainToken))->first();
    }

    /**
     * Add the user to the workspace. Idempotent, so a double-clicked link is harmless.
     */
    public function accept(WorkspaceInvitation $invitation, User $user): WorkspaceMember
    {
        return DB::transaction(function () use ($invitation, $user) {
            $member = WorkspaceMember::updateOrCreate(
                [
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->id,
                ],
                ['role' => $invitation->role]
            );

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ]);

            return $member;
        });
    }

    /**
     * Mail the invitation.
     *
     * Sent on-demand rather than to a User, because the invitee may not have an account
     * yet — and should not get one until they actually accept.
     */
    private function send(WorkspaceInvitation $invitation, User $inviter): void
    {
        Notification::route('mail', $invitation->email)->notify(
            new WorkspaceInvitationNotification(
                acceptUrl: route('invitations.show', $invitation->plainToken),
                workspaceName: $invitation->workspace->name,
                inviterName: $inviter->name,
                roleLabel: $invitation->role->label(),
            )
        );
    }
}
