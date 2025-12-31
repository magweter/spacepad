<?php

namespace App\Http\Controllers;

use App\Services\OutlookService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

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
        $connectCode = $user->getConnectCode();
        $user->load(['outlookAccounts', 'googleAccounts', 'caldavAccounts', 'displays']);

        logger()->info('Dashboard page accessed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'outlook_accounts_count' => $user->outlookAccounts->count(),
            'google_accounts_count' => $user->googleAccounts->count(),
            'caldav_accounts_count' => $user->caldavAccounts->count(),
            'displays_count' => $user->displays->count(),
            'ip' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 100),
        ]);

        return view('pages.dashboard', [
            'outlookAccounts' => $user->outlookAccounts,
            'googleAccounts' => $user->googleAccounts,
            'caldavAccounts' => $user->caldavAccounts,
            'displays' => $user->displays,
            'connectCode' => $connectCode,
        ]);
    }
}
