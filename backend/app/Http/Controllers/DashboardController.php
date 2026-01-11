<?php

namespace App\Http\Controllers;

use App\Services\OutlookService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use App\Services\InstanceService;
use App\Models\Display;

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
        $user->load(['outlookAccounts', 'googleAccounts', 'caldavAccounts', 'workspaces']);
        
        // Get connect code for user
        $connectCode = $user->getConnectCode();

        // Get displays from all workspaces user is a member of
        $workspaceIds = $user->workspaces->pluck('id');
        $displays = Display::whereIn('workspace_id', $workspaceIds)
            ->with(['workspace', 'calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount'])
            ->get()
            ->groupBy('workspace_id');

        logger()->info('Dashboard page accessed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'outlook_accounts_count' => $user->outlookAccounts->count(),
            'google_accounts_count' => $user->googleAccounts->count(),
            'caldav_accounts_count' => $user->caldavAccounts->count(),
            'displays_count' => $displays->flatten()->count(),
            'workspaces_count' => $user->workspaces->count(),
            'ip' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 100),
        ]);

        $isSelfHosted = config('settings.is_self_hosted');
        
        return view('pages.dashboard', [
            'outlookAccounts' => $user->outlookAccounts,
            'googleAccounts' => $user->googleAccounts,
            'caldavAccounts' => $user->caldavAccounts,
            'displays' => $displays, // Grouped by workspace
            'displaysFlat' => $displays->flatten(), // Flat list for compatibility
            'workspaces' => $user->workspaces,
            'connectCode' => $connectCode,
            'primaryWorkspace' => $user->primaryWorkspace(),
            'version' => config('settings.version', 'dev'),
            'appEnv' => config('app.env', 'production'),
            'appUrl' => config('app.url'),
            'isSelfHosted' => $isSelfHosted,
        ]);
    }
}
