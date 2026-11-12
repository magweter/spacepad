<?php

namespace App\Http\Controllers;

use App\Enums\FunnelStep;
use App\Enums\WorkspaceRole;
use App\Models\WorkspaceJoinRequest;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceJoinApproved;
use App\Services\Funnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An owner or admin reviewing a colleague's request to join their workspace.
 *
 * Approving adds a member, which is the same thing as accepting an invitation, so it follows the
 * same rule: inviting colleagues is part of Pro.
 */
class JoinRequestController extends Controller
{
    public function show(Request $request, WorkspaceJoinRequest $joinRequest): Response
    {
        $workspace = $joinRequest->workspace;
        abort_unless($workspace->hasMember($request->user()), 403);

        $role = $workspace->members()->whereKey($request->user()->id)->first()?->pivot?->role;
        $canInvite = $role && WorkspaceRole::from(is_string($role) ? $role : $role->value)->canInvite();

        return Inertia::render('JoinRequests/Review', [
            'request' => [
                'id' => $joinRequest->id,
                'name' => $joinRequest->user->name,
                'email' => $joinRequest->user->email,
                'status' => $joinRequest->status,
                'askedAt' => $joinRequest->created_at->diffForHumans(),
            ],
            'workspace' => ['name' => $workspace->name],
            'canDecide' => (bool) $canInvite,
            'needsPro' => $canInvite && ! $request->user()->can('invite', $workspace),
            'urls' => [
                'approve' => route('join-requests.approve', $joinRequest),
                'decline' => route('join-requests.decline', $joinRequest),
                'upgrade' => route('workspaces.members'),
                'dashboard' => route('dashboard'),
            ],
        ]);
    }

    public function approve(Request $request, WorkspaceJoinRequest $joinRequest): RedirectResponse
    {
        $workspace = $joinRequest->workspace;
        $this->authorize('invite', $workspace);

        if ($joinRequest->isPending()) {
            DB::transaction(function () use ($request, $joinRequest, $workspace) {
                WorkspaceMember::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'user_id' => $joinRequest->user_id],
                    ['role' => WorkspaceRole::MEMBER]
                );

                $joinRequest->update(['status' => WorkspaceJoinRequest::APPROVED, 'decided_by_user_id' => $request->user()->id, 'decided_at' => now()]);

                $requester = $joinRequest->user;

                // The empty workspace they got at sign-up would otherwise be where they land.
                $requester->ownedWorkspaces()->get()
                    ->filter(fn ($own) => $own->id !== $workspace->id && $own->isEmpty() && ! $own->hasActiveSubscription())
                    ->each(function ($own) {
                        WorkspaceMember::where('workspace_id', $own->id)->delete();
                        $own->delete();
                    });

                if ($requester->onboarding_completed_at === null) {
                    $requester->update(['onboarding_completed_at' => now()]);
                }
            });

            $joinRequest->user->notify(new WorkspaceJoinApproved($workspace->name, route('dashboard')));
            Funnel::record(FunnelStep::JOIN_APPROVED, $workspace, $request->user(), ['requester' => $joinRequest->user_id]);
        }

        return redirect()->route('join-requests.show', $joinRequest)->with('success', "{$joinRequest->user->name} is a member of {$workspace->name} now.");
    }

    public function decline(Request $request, WorkspaceJoinRequest $joinRequest): RedirectResponse
    {
        $workspace = $joinRequest->workspace;
        $role = $workspace->members()->whereKey($request->user()->id)->first()?->pivot?->role;
        abort_unless($role && WorkspaceRole::from(is_string($role) ? $role : $role->value)->canInvite(), 403);

        if ($joinRequest->isPending()) {
            $joinRequest->update(['status' => WorkspaceJoinRequest::DECLINED, 'decided_by_user_id' => $request->user()->id, 'decided_at' => now()]);
        }

        return redirect()->route('join-requests.show', $joinRequest)->with('info', 'Request declined. They keep a workspace of their own.');
    }
}
