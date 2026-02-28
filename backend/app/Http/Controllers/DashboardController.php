<?php

namespace App\Http\Controllers;

use App\Services\OutlookService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use App\Services\InstanceService;
use App\Models\Display;
use App\Models\Calendar;
use App\Models\OutlookAccount;
use App\Models\GoogleAccount;
use App\Models\CalDAVAccount;
use App\Models\Board;

class DashboardController extends Controller
{
    public function __construct(protected OutlookService $outlookService)
    {
    }

    /**
     * @return Application|Factory|View
     * @throws \Exception
     */
    public function __invoke(): View|Factory|Application
    {
        $user = auth()->user();
        
        // Load workspaces with pivot data (role) - this includes all workspaces user is a member of
        $workspaces = $user->workspaces()->withPivot('role')->get();
        
        // Get selected workspace (from session or default to primary)
        $selectedWorkspace = $user->getSelectedWorkspace();
        
        // Get connect code from workspace owner (or current user if no workspace selected)
        $connectCode = null;
        if ($selectedWorkspace) {
            $workspaceOwner = $selectedWorkspace->owners()->first();
            if ($workspaceOwner) {
                $connectCode = $workspaceOwner->getConnectCode();
            }
        }
        // Fallback to current user's connect code if no workspace or owner found
        if (!$connectCode) {
            $connectCode = $user->getConnectCode();
        }
        
        // Get displays from selected workspace only
        if ($selectedWorkspace) {
            $displays = Display::where('workspace_id', $selectedWorkspace->id)
                ->with(['workspace', 'calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount'])
                ->get();
            
            // Get boards for the selected workspace
            $boards = Board::where('workspace_id', $selectedWorkspace->id)
                ->with(['user', 'displays'])
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
            $outlookAccounts = collect();
            $googleAccounts = collect();
            $caldavAccounts = collect();
        }

        logger()->info('Dashboard page accessed', [
            'user_id' => $user->id,
            'email' => $user->email,
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
        
        return view('pages.dashboard', [
            'outlookAccounts' => $outlookAccounts,
            'googleAccounts' => $googleAccounts,
            'caldavAccounts' => $caldavAccounts,
            'displays' => $displays,
            'boards' => $boards,
            'workspaces' => $workspaces,
            'selectedWorkspace' => $selectedWorkspace,
            'connectCode' => $connectCode,
            'primaryWorkspace' => $user->primaryWorkspace(),
            'version' => config('settings.version', 'dev'),
            'appEnv' => config('app.env', 'production'),
            'appUrl' => config('app.url'),
            'isSelfHosted' => $isSelfHosted,
        ]);
    }
}
