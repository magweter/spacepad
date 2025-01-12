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
        $outlookAccounts = auth()->user()->outlookAccounts;
        $displays = auth()->user()->displays;
        $connectCode = auth()->user()->getConnectCode();

        return view('pages.dashboard', [
            'outlookAccounts' => $outlookAccounts,
            'displays' => $displays,
            'connectCode' => $connectCode,
        ]);
    }
}
