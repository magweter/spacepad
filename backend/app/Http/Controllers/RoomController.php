<?php

namespace App\Http\Controllers;

use App\Services\OutlookService;
use App\Services\GoogleService;
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
    ) {
    }

    public function outlook(string $id): View|Factory|Application
    {
        try {
            $account = auth()->user()->outlookAccounts()->findOrFail($id);
            $rooms = $this->outlookService->fetchRooms($account);
            
            return view('components.rooms.picker', [
                'rooms' => collect($rooms)->map(function (array $room) {
                    return [
                        'emailAddress' => $room['emailAddress'],
                        'name' => $room['displayName']
                    ];
                })->toArray()
            ]);
        } catch (ConnectionException $e) {
            logger()->error('Outlook API connection error: ' . $e->getMessage());
            return view('components.rooms.picker', [
                'rooms' => [],
                'error' => 'Could not connect to Outlook. Please try again later.'
            ]);
        } catch (\Exception $e) {
            logger()->error('Outlook rooms fetch error: ' . $e->getMessage());
            return view('components.rooms.picker', [
                'rooms' => [],
                'error' => 'Could not fetch rooms from Outlook. Please check your permissions and try again.'
            ]);
        }
    }

    public function google(string $id): View|Factory|Application
    {
        try {
            $account = auth()->user()->googleAccounts()->findOrFail($id);
            $rooms = $this->googleService->fetchRooms($account);
            
            return view('components.rooms.picker', [
                'rooms' => collect($rooms)->map(function ($room) {
                    return [
                        'emailAddress' => $room->getResourceEmail(),
                        'name' => $room->getResourceName(),
                    ];
                })->toArray()
            ]);
        } catch (GoogleException $e) {
            logger()->error('Google API error: ' . $e->getMessage());
            
            // Check for insufficient permissions error
            if (str_contains($e->getMessage(), 'insufficientPermissions') || 
                str_contains($e->getMessage(), 'ACCESS_TOKEN_SCOPE_INSUFFICIENT')) {
                return view('components.rooms.picker', [
                    'rooms' => [],
                    'error' => 'Insufficient permissions to access Google Calendar. Please ensure you have granted all required permissions during authentication.'
                ]);
            }
            
            return view('components.rooms.picker', [
                'rooms' => [],
                'error' => 'Could not fetch rooms from Google. Please check your permissions and try again.'
            ]);
        } catch (\Exception $e) {
            logger()->error('Google rooms fetch error: ' . $e->getMessage());
            return view('components.rooms.picker', [
                'rooms' => [],
                'error' => 'Could not fetch rooms from Google. Please try again later.'
            ]);
        }
    }
}
