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
        $connectCode = auth()->user()->getConnectCode();
        $user = auth()->user()->load(['outlookAccounts', 'googleAccounts', 'displays']);

        return view('pages.dashboard', [
            'outlookAccounts' => $user->outlookAccounts,
            'googleAccounts' => $user->googleAccounts,
            'displays' => $user->displays,
            'connectCode' => $connectCode,
        ]);
    }
}
