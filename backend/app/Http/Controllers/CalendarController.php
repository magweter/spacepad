<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Synchronization;
use App\Services\GoogleService;
use App\Services\OutlookService;
use App\Services\CalDAVService;
use Google\Service\Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected CalDAVService $caldavService
    ) {
    }

    public function google(string $id): View|Factory|Application
    {
        $account = auth()->user()->googleAccounts()->findOrFail($id);
        return view('components.calendars.picker', [
            'calendars' => $account->getCalendars()
        ]);
    }

    public function outlook(string $id): View|Factory|Application
    {
        $account = auth()->user()->outlookAccounts()->findOrFail($id);
        return view('components.calendars.picker', [
            'calendars' => $account->getCalendars()
        ]);
    }

    public function caldav(string $id): View|Factory|Application
    {
        $account = auth()->user()->caldavAccounts()->findOrFail($id);
        return view('components.calendars.picker', [
            'calendars' => $account->getCalendars()
        ]);
    }
}
