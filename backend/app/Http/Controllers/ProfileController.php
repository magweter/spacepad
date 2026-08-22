<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(protected WorkspaceService $workspaces) {}

    /**
     * The personal account page.
     *
     * Deliberately workspace-independent: identity and account deletion only. Subscription
     * and usage live on the workspace they belong to (see WorkspaceMemberController), which
     * also keeps this page meaningful for someone who is in several workspaces.
     */
    public function show(): View
    {
        return view('pages.profile');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_email' => ['required', 'email'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if ($request->input('confirm_email') !== $user->email) {
            return back()->withErrors(['confirm_email' => 'Email address does not match your account.']);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();

            // Deliberately workspace-first: data lives in a workspace, and user_id only
            // records who created it. Deleting by $user->displays would take a shared
            // workspace's displays down with a single departing colleague.
            $this->workspaces->detachUserFromAllWorkspaces($user);

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

            User::where('id', $userId)->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your account and all associated data have been permanently deleted.');
    }
}
