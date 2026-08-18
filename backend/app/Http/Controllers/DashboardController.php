<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\CalDAVAccount;
use App\Models\Device;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Services\FunnelTracking;
use App\Services\OutlookService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(protected OutlookService $outlookService) {}

    /**
     * @throws \Exception
     */
    public function __invoke(): View|Factory|Application
    {
        $user = auth()->user();

        // Load workspaces with pivot data (role) - this includes all workspaces user is a member of
        $workspaces = $user->workspaces()->withPivot('role')->get();

        // Get selected workspace (from session or default to primary)
        $selectedWorkspace = $user->getSelectedWorkspace();

        // The code belongs to the workspace on screen, so a tablet paired from here lands in
        // that workspace. This previously used the workspace *owner's* personal code while
        // pairing bound the device to the owner's primary workspace, so a member pairing a
        // tablet while workspace B was selected ended up with a device in workspace A.
        $connectCode = $selectedWorkspace?->getConnectCode($user);

        // Get displays from selected workspace only
        if ($selectedWorkspace) {
            // `settings` and `profile` are eager-loaded because the overview shows the linked profile
            // and whether the display deviates from it — without them that is a query per row.
            $displays = Display::where('workspace_id', $selectedWorkspace->id)
                ->with(['workspace', 'calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount', 'calendar.room', 'settings', 'profile'])
                ->get();

            // Get boards for the selected workspace
            $boards = Board::where('workspace_id', $selectedWorkspace->id)
                ->with(['user', 'displays'])
                ->orderBy('name')
                ->get();

            // Get display profiles for the selected workspace
            $profiles = DisplayProfile::where('workspace_id', $selectedWorkspace->id)
                ->withCount('displays')
                ->with('user')
                ->orderBy('name')
                ->get();

            // Get accounts for the selected workspace
            $outlookAccounts = OutlookAccount::where('workspace_id', $selectedWorkspace->id)
                ->get();
            $googleAccounts = GoogleAccount::where('workspace_id', $selectedWorkspace->id)
                ->get();
            $caldavAccounts = CalDAVAccount::where('workspace_id', $selectedWorkspace->id)
                ->get();
        } else {
            $displays = collect();
            $boards = collect();
            $profiles = collect();
            $outlookAccounts = collect();
            $googleAccounts = collect();
            $caldavAccounts = collect();
        }

        logger()->info('Dashboard page accessed', [
            'user_id' => $user->id,
            'outlook_accounts_count' => $outlookAccounts->count(),
            'google_accounts_count' => $googleAccounts->count(),
            'caldav_accounts_count' => $caldavAccounts->count(),
            'displays_count' => $displays->count(),
            'workspaces_count' => $workspaces->count(),
            'selected_workspace_id' => $selectedWorkspace?->id,
            'ip' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 100),
        ]);

        $isSelfHosted = config('settings.is_self_hosted');

        $hasDisplay = $displays->isNotEmpty();
        $hasDevice = $selectedWorkspace
            ? Device::where('workspace_id', $selectedWorkspace->id)->exists()
            : false;

        // Pairing happens on the tablet, against the API, so the funnel milestone for it can
        // only be picked up here, the next time the dashboard is opened.
        if ($hasDevice) {
            FunnelTracking::devicePaired($selectedWorkspace);
        }

        return view('pages.dashboard', [
            'outlookAccounts' => $outlookAccounts,
            'googleAccounts' => $googleAccounts,
            'caldavAccounts' => $caldavAccounts,
            'displays' => $displays,
            'boards' => $boards,
            'profiles' => $profiles,
            'workspaces' => $workspaces,
            'selectedWorkspace' => $selectedWorkspace,
            'connectCode' => $connectCode,
            'primaryWorkspace' => $user->primaryWorkspace(),
            'version' => config('settings.version', 'dev'),
            'appEnv' => config('app.env', 'production'),
            'appUrl' => config('app.url'),
            'isSelfHosted' => $isSelfHosted,
            'hasDisplay' => $hasDisplay,
            'hasDevice' => $hasDevice,
        ]);
    }
}
