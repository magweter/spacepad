<?php

namespace App\Http\Controllers;

use App\Enums\Provider;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Services\GoogleService;
use App\Services\OutlookService;
use Google\Service\Exception as GoogleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;

class RoomController extends Controller
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService
    ) {}

    public function outlook(string $id): View|Factory|Application
    {
        // Resolved and authorized outside the try, so an authorization failure surfaces as
        // a 403 instead of being swallowed into a "could not fetch rooms" view.
        $account = OutlookAccount::findOrFail($id);
        $this->authorize('view', $account);

        try {
            $rooms = $this->outlookService->fetchRooms($account);

            return view('components.rooms.picker', [
                'rooms' => collect($rooms)->map(function (array $room) {
                    return [
                        'emailAddress' => $room['emailAddress'],
                        'name' => $room['displayName'],
                    ];
                })->toArray(),
                'type' => Provider::OUTLOOK,
            ]);
        } catch (ConnectionException $e) {
            logger()->error('Outlook API connection error: '.$e->getMessage());

            return view('components.rooms.picker', [
                'rooms' => [],
                'type' => Provider::OUTLOOK,
                'error' => 'Could not connect to Outlook. Please try again later.',
            ]);
        } catch (\Exception $e) {
            logger()->error('Outlook rooms fetch error: '.$e->getMessage());

            return view('components.rooms.picker', [
                'rooms' => [],
                'type' => Provider::OUTLOOK,
                'error' => 'Could not fetch rooms from Outlook. Please check your permissions and try again.',
            ]);
        }
    }

    public function google(string $id): View|Factory|Application
    {
        $account = GoogleAccount::findOrFail($id);
        $this->authorize('view', $account);

        try {
            $rooms = $this->googleService->fetchRooms($account);

            return view('components.rooms.picker', [
                'rooms' => collect($rooms)->map(function ($room) {
                    return [
                        'emailAddress' => $room->getResourceEmail(),
                        'name' => $room->getResourceName(),
                    ];
                })->toArray(),
                'type' => Provider::GOOGLE,
            ]);
        } catch (GoogleException $e) {
            logger()->error('Google API error: '.$e->getMessage());

            // Check for insufficient permissions error
            if (str_contains($e->getMessage(), 'insufficientPermissions') ||
                str_contains($e->getMessage(), 'ACCESS_TOKEN_SCOPE_INSUFFICIENT')) {
                return view('components.rooms.picker', [
                    'rooms' => [],
                    'type' => Provider::GOOGLE,
                    'error' => 'Insufficient permissions to access Google Calendar. Please ensure you have granted all required permissions during authentication.',
                ]);
            }

            return view('components.rooms.picker', [
                'rooms' => [],
                'type' => Provider::GOOGLE,
                'error' => 'Could not fetch rooms from Google. Please check your permissions and try again.',
            ]);
        } catch (\Exception $e) {
            logger()->error('Google rooms fetch error: '.$e->getMessage());

            return view('components.rooms.picker', [
                'rooms' => [],
                'type' => Provider::GOOGLE,
                'error' => 'Could not fetch rooms from Google. Please try again later.',
            ]);
        }
    }
}
