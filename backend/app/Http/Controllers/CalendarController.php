<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Synchronization;
use App\Services\GoogleService;
use App\Services\OutlookService;
use App\Services\CalDAVService;
use Google\Service\Exception as GoogleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;

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
        try {
            $account = auth()->user()->googleAccounts()->findOrFail($id);
            $calendars = $this->googleService->fetchCalendars($account);

            return view('components.calendars.picker', [
                'calendars' => collect($calendars)->map(function ($calendar) {
                    return [
                        'id' => $calendar->getId(),
                        'name' => $calendar->getSummary(),
                    ];
                })->toArray()
            ]);
        } catch (GoogleException $e) {
            logger()->error('Google API error: ' . $e->getMessage());

            // Check for insufficient permissions error
            if (str_contains($e->getMessage(), 'insufficientPermissions') ||
                str_contains($e->getMessage(), 'ACCESS_TOKEN_SCOPE_INSUFFICIENT')) {
                return view('components.calendars.picker', [
                    'calendars' => [],
                    'error' => 'Insufficient permissions to access Google Calendar. Please ensure you have granted all required permissions during authentication.'
                ]);
            }

            return view('components.calendars.picker', [
                'calendars' => [],
                'error' => 'Could not fetch calendars from Google. Please check your permissions and try again.'
            ]);
        } catch (\Exception $e) {
            logger()->error('Google calendars fetch error: ' . $e->getMessage());
            return view('components.calendars.picker', [
                'calendars' => [],
                'error' => 'Could not fetch calendars from Google. Please try again later.'
            ]);
        }
    }

    public function outlook(string $id): View|Factory|Application
    {
        try {
            $account = auth()->user()->outlookAccounts()->findOrFail($id);
            $calendars = $this->outlookService->fetchCalendars($account);

            return view('components.calendars.picker', [
                'calendars' => collect($calendars)->map(function (array $calendar) {
                    return [
                        'id' => $calendar['id'],
                        'name' => $calendar['name']
                    ];
                })->toArray()
            ]);
        } catch (ConnectionException $e) {
            logger()->error('Outlook API connection error: ' . $e->getMessage());
            return view('components.calendars.picker', [
                'calendars' => [],
                'error' => 'Could not connect to Outlook. Please try again later.'
            ]);
        } catch (\Exception $e) {
            logger()->error('Outlook calendars fetch error: ' . $e->getMessage());
            return view('components.calendars.picker', [
                'calendars' => [],
                'error' => 'Could not fetch calendars from Outlook. Please check your permissions and try again.'
            ]);
        }
    }

    public function caldav(string $id): View|Factory|Application
    {
        try {
            $account = auth()->user()->caldavAccounts()->findOrFail($id);
            $calendars = app(CalDAVService::class)->fetchCalendars($account);
            return view('components.calendars.picker', [
                'calendars' => collect($calendars)->map(function ($calendar) {
                    return [
                        'id' => $calendar['id'],
                        'name' => $calendar['name']
                    ];
                })->toArray()
            ]);
        } catch (\Exception $e) {
            logger()->error('CalDAV calendars fetch error: ' . $e->getMessage());
            return view('components.calendars.picker', [
                'calendars' => [],
                'error' => 'Could not fetch calendars from CalDAV server. Please check your connection and try again.'
            ]);
        }
    }
}
