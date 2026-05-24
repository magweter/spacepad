<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        $selectedWorkspace = $user->getSelectedWorkspace();
        $usageBreakdown = $selectedWorkspace?->getUsageBreakdown();

        return view('pages.profile', [
            'usageBreakdown' => $usageBreakdown,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_email' => ['required', 'email'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->input('confirm_email') !== $user->email) {
            return back()->withErrors(['confirm_email' => 'Email address does not match your account.']);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();

            foreach ($user->displays as $display) {
                $display->eventSubscriptions()->delete();
                $display->settings()->delete();
                $display->events()->delete();
                $display->devices()->delete();
                $display->delete();
            }

            $user->devices()->delete();
            $user->rooms()->delete();

            foreach ($user->outlookAccounts as $account) {
                foreach ($account->calendars as $calendar) {
                    $calendar->events()->delete();
                    $calendar->delete();
                }
                $account->delete();
            }

            foreach ($user->googleAccounts as $account) {
                foreach ($account->calendars as $calendar) {
                    $calendar->events()->delete();
                    $calendar->delete();
                }
                $account->delete();
            }

            foreach ($user->caldavAccounts as $account) {
                foreach ($account->calendars as $calendar) {
                    $calendar->events()->delete();
                    $calendar->delete();
                }
                $account->delete();
            }

            foreach ($user->ownedWorkspaces()->get() as $workspace) {
                $otherMembers = $workspace->members()->where('user_id', '!=', $user->id)->get();

                if ($otherMembers->isNotEmpty()) {
                    $newOwner = $otherMembers->first(fn ($m) => $m->pivot->role === WorkspaceRole::ADMIN->value)
                        ?? $otherMembers->first();

                    WorkspaceMember::where('workspace_id', $workspace->id)
                        ->where('user_id', $newOwner->id)
                        ->update(['role' => WorkspaceRole::OWNER]);
                } else {
                    foreach ($workspace->displays as $display) {
                        $display->eventSubscriptions()->delete();
                        $display->settings()->delete();
                        $display->events()->delete();
                        $display->devices()->delete();
                        $display->delete();
                    }
                    $workspace->devices()->delete();
                    foreach ($workspace->calendars as $calendar) {
                        $calendar->events()->delete();
                        $calendar->delete();
                    }
                    $workspace->rooms()->delete();
                    WorkspaceMember::where('workspace_id', $workspace->id)->delete();
                    $workspace->delete();
                }
            }

            WorkspaceMember::where('user_id', $user->id)->delete();

            if (method_exists($user, 'subscriptions')) {
                $user->subscriptions()->delete();
            }

            if (method_exists($user, 'customer')) {
                $user->customer()->delete();
            }

            $userId = $user->id;

            logger()->info('User account deleted by self', [
                'deleted_user_id' => $userId,
            ]);

            \App\Models\User::where('id', $userId)->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your account and all associated data have been permanently deleted.');
    }
}
