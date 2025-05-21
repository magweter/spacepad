<?php

namespace App\Http\Controllers;

use App\Enums\Provider;
use App\Enums\DisplayStatus;
use App\Events\UserOnboarded;
use App\Http\Requests\CreateDisplayRequest;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Services\OutlookService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\GoogleAccount;
use App\Services\GoogleService;

class DisplayController extends Controller
{
    public function __construct(protected OutlookService $outlookService, protected GoogleService $googleService)
    {
    }

    public function create(Request $request): View|Application|Factory
    {
        $user = auth()->user()->load(['outlookAccounts', 'googleAccounts', 'displays']);

        return view('pages.displays.create', [
            'outlookAccounts' => $user->outlookAccounts,
            'googleAccounts' => $user->googleAccounts,
            'displays' => $user->displays,
        ]);
    }

    /**
     * @throws \Exception
     */
    public function store(CreateDisplayRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

        $display = DB::transaction(function () use ($validatedData) {
            $provider = $validatedData['provider'];
            $accountId = $validatedData['account'];

            // Get the appropriate account model based on provider
            $account = match($provider) {
                'outlook' => OutlookAccount::findOrFail($accountId),
                'google' => GoogleAccount::findOrFail($accountId),
                default => throw new \InvalidArgumentException('Invalid provider')
            };

            // Handle room or calendar selection
            $calendar = $this->createCalendar($validatedData, $account);

            return Display::firstOrCreate([
                'calendar_id' => $calendar->id,
            ], [
                'user_id' => auth()->id(),
                'name' => $validatedData['name'],
                'display_name' => $validatedData['displayName'],
                'status' => DisplayStatus::READY,
                'calendar_id' => $calendar->id,
            ]);
        });

        if ($display) {
            event(new UserOnboarded($request->user(), $display));
        }

        // Redirect back with a success message
        return redirect()->route('dashboard')->with('status', $display ?
            'Display created! Now enter the connect code in the app on your tablet to connect it to the display.' :
            'Display could not be created. Please try again later.'
        );
    }

    public function updateStatus(Request $request, Display $display): RedirectResponse
    {
        $this->authorize('update', $display);

        $data = $request->validate([
            'status' => 'required|in:active,deactivated'
        ]);

        $display->update(['status' => $data['status']]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Display status has been changed.');
    }

    public function delete(Display $display): RedirectResponse
    {
        $this->authorize('delete', $display);

        $display->eventSubscriptions()->delete();
        $display->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Display has successfully been deleted.');
    }

    private function createCalendar(array $validatedData, mixed $account): Calendar
    {
        $provider = $validatedData['provider'];
        $accountId = $validatedData['account'];

        if (isset($validatedData['room'])) {
            $roomData = explode(',', $validatedData['room']);
            $calendarId = match ($provider) {
                'outlook' => $this->outlookService->fetchCalendarByUser($account, $roomData[0])['id'],
                'google' => $roomData[0],
                default => throw new \InvalidArgumentException('Invalid provider')
            };

            $calendar = Calendar::firstOrCreate([
                'calendar_id' => $calendarId,
                'user_id' => auth()->id(),
            ], [
                'calendar_id' => $calendarId,
                'user_id' => auth()->id(),
                "{$provider}_account_id" => $accountId,
                'name' => $roomData[1],
            ]);

            Room::firstOrCreate([
                'email_address' => $roomData[0],
                'user_id' => auth()->id(),
            ], [
                'email_address' => $roomData[0],
                'user_id' => auth()->id(),
                'calendar_id' => $calendar->id,
                'name' => $roomData[1],
            ]);

            return $calendar;
        }

        $calendarData = explode(',', $validatedData['calendar']);
        return Calendar::firstOrCreate([
            'calendar_id' => $calendarData[0],
            'user_id' => auth()->id(),
        ], [
            'user_id' => auth()->id(),
            "{$provider}_account_id" => $accountId,
            'calendar_id' => $calendarData[0],
            'name' => $calendarData[1],
        ]);
    }
}
