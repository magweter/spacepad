# Workspaces

A workspace is the tenant. Displays, devices, calendars, rooms, boards, display profiles and
calendar accounts all belong to a workspace, and billing is measured against it.

Every new account gets one workspace automatically (`User::boot()`), except accounts created by
accepting an invitation — those join an existing workspace instead
(`User::createForInvitation()`).

## Data model

- `workspaces` — name, the billing flags (`is_unlimited`, `is_manually_billed`,
  `manual_billing_unit_price`) and `billing_owner_user_id`. There is **no** `owner_id`: ownership
  is a role on the membership pivot.
- `workspace_members` — the pivot, with a `role` of `owner`, `admin` or `member` and a unique
  index on `(workspace_id, user_id)`.
- `workspace_invitations` — one pending invitation per email per workspace. The `token` column
  holds a **sha256 hash**; the plaintext only ever exists in the email, because the link is a
  credential.

Workspace-scoped tables carry `workspace_id`. They also carry `user_id`, but that is *provenance*
("created by"), not ownership: it is nullable and nulls out on delete, so a colleague deleting
their account cannot take the team's displays with them.

## Roles

| Ability | Owner | Admin | Member |
|---|---|---|---|
| Manage displays, boards, profiles, calendar accounts | ✓ | ✓ | ✓ |
| Rename the workspace | ✓ | ✓ | |
| Invite / withdraw invitations | ✓ | ✓ | |
| Change roles, remove members | ✓ | | |
| Billing | ✓ | | |

Content abilities live in `DisplayPolicy`, `BoardPolicy`, `DisplayProfilePolicy` and the three
account policies, all of which check workspace membership. Workspace administration lives in
`WorkspacePolicy`. Intent-named helpers on `WorkspaceRole` (`canInvite()`, `canManageMembers()`,
`canManageBilling()`) keep call sites from having to reason about the matrix.

A workspace always keeps at least one owner: the last one cannot be demoted, removed, or leave
without nominating a successor.

## Current workspace

There is no global scope or middleware. Controllers call
`auth()->user()->getSelectedWorkspace()`, which reads the `selected_workspace_id` session key,
validates membership, and otherwise falls back to `primaryWorkspace()`.

## Invitations

1. An owner or admin invites an email address (`WorkspaceInvitationService::invite()`), which
   sends `WorkspaceInvitationNotification` on demand — the invitee may not have an account yet.
2. The emailed link is the authentication: possession of the token proves mailbox control, the
   same trust level as the magic login link. Routing invitees through `/login` instead would
   auto-create an account *and* a personal workspace, which is the problem shared workspaces
   solve.
3. `AcceptInvitationController` handles guests, existing users, and people signed in under a
   different address (who get an explicit mismatch screen, never a silent join).
4. Accepting can optionally move the invitee's own data across
   (`WorkspaceTransferService::move()`).

The notification is deliberately **not** `ShouldQueue`: production runs `schedule:work` but no
`queue:work`, so queueing would silently swallow invitations.

## Billing

`Workspace::hasPro()` is authoritative. `User::hasProForCurrentWorkspace()`,
`hasProForWorkspace()` and `shouldUpgradeForCurrentWorkspace()` are thin delegates;
`User::hasPro()` is a deprecated delegate.

Usage is `Workspace::calculateUsage($displays, $boards)` — displays x1, boards x2 — the single
home for that formula. `UpdateLemonSqueezySubscriptions` pushes it per workspace.

`User` keeps the `Billable` trait for now. The vendor webhook controller resolves the billable
from `meta.custom_data`, which was fixed when the checkout was created, so every pre-cutover
subscription keeps arriving addressed to a user. `RemapLegacySubscriptionBillable` and
`RemapLegacyOrderBillable` move those rows back onto the workspace and log a warning; once that
warning stops appearing, the trait can come off `User`.

`users.is_unlimited` also stays, because it is part of the self-hosted heartbeat payload
(`InstanceService`, `UserData`, `InstanceHeartbeatRequest`). `usage_type` stays on the user too:
it is onboarding state and drives self-hosted licensing, not a plan tier.

Before migrating a production database, run `php artisan app:audit-billing-move`. Two of its
findings need a decision rather than code: a user who owns several workspaces keeps Pro on only
one, and the corrected usage figure is lower for anyone who had joined a second workspace, which
lowers their next invoice.

## Device pairing

Connect codes are workspace-scoped (`Workspace::getConnectCode($user)` /
`Workspace::pullConnectCode()`), so a tablet lands in the workspace that was on screen. The
tablet API is unchanged — still a 6-digit code — so no app release is needed. Device API
requests are scoped to `$device->workspace_id`, falling back to the pairing user's workspaces
only for devices paired before that column was set.

## Consolidating existing accounts

- Self-service: move data while accepting an invitation, then delete the emptied workspace.
- Admin: **Merge workspaces** in the admin panel (`AdminMergeController`). Preview first — it is
  irreversible. It never touches subscriptions and never de-duplicates calendar accounts
  (uniqueness on those tables was removed deliberately; the OAuth tokens differ, and re-pointing
  calendars would break sync).
