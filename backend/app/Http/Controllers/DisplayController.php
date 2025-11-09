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
use App\Models\CalDAVAccount;
use App\Services\OutlookService;
use App\Services\GoogleService;
use App\Services\CalDAVService;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\GoogleAccount;

class DisplayController extends Controller
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected CalDAVService $caldavService
    ) {
    }

    public function create(): View
    {
        $outlookAccounts = auth()->user()->outlookAccounts;
        $googleAccounts = auth()->user()->googleAccounts;
        $caldavAccounts = auth()->user()->caldavAccounts;

        return view('pages.displays.create', [
            'outlookAccounts' => $outlookAccounts,
            'googleAccounts' => $googleAccounts,
            'caldavAccounts' => $caldavAccounts,
        ]);
    }

    /**
     * @throws Exception
     */
    public function store(CreateDisplayRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

        $provider = $validatedData['provider'];
        $accountId = $validatedData['account'];

        // Check on access to create multiple displays
        if (auth()->user()->shouldUpgrade()) {
            return redirect()->back()->with('error', 'You require an active Pro license to create multiple displays.');
        }

        // Validate the existence of the appropriate account based on provider
        match ($provider) {
            'outlook' => OutlookAccount::findOrFail($accountId),
            'google' => GoogleAccount::findOrFail($accountId),
            'caldav' => CalDAVAccount::findOrFail($accountId),
            default => throw new \InvalidArgumentException('Invalid provider')
        };

        $display = DB::transaction(function () use ($validatedData) {
            // Handle room or calendar selection
            $calendar = $this->createCalendar($validatedData);

            return Display::create([
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

        return redirect()->route('dashboard')->with($display ? 'success' : 'error', $display ?
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

        // Get the calendar associated with the display
        $calendar = $display->calendar;

        // Delete the display
        $display->eventSubscriptions()->delete();
        $display->delete();

        // Delete the calendar if it has no displays
        if ($calendar && $calendar->displays()->count() === 0) {
            $calendar->delete();
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Display has successfully been deleted.');
    }

    private function createCalendar(array $validatedData): Calendar
    {
        $provider = $validatedData['provider'];
        $accountId = $validatedData['account'];

        if (isset($validatedData['room'])) {
            $roomData = explode(',', $validatedData['room']);
            $calendarId = $roomData[0];
            $calendarName = $this->extractCalendarName($roomData[1] ?? '');

            $calendar = Calendar::firstOrCreate([
                'calendar_id' => $calendarId,
                'user_id' => auth()->id(),
            ], [
                'calendar_id' => $calendarId,
                'user_id' => auth()->id(),
                "{$provider}_account_id" => $accountId,
                'name' => $calendarName,
            ]);

            Room::firstOrCreate([
                'email_address' => $calendarId,
                'user_id' => auth()->id(),
            ], [
                'email_address' => $calendarId,
                'user_id' => auth()->id(),
                'calendar_id' => $calendar->id,
                'name' => $calendarName,
            ]);

            return $calendar;
        }

        $calendarData = explode(',', $validatedData['calendar']);
        $calendarName = $this->extractCalendarName($calendarData[1] ?? '');
        
        return Calendar::firstOrCreate([
            'calendar_id' => $calendarData[0],
            'user_id' => auth()->id(),
        ], [
            'user_id' => auth()->id(),
            "{$provider}_account_id" => $accountId,
            'calendar_id' => $calendarData[0],
            'name' => $calendarName,
        ]);
    }

    /**
     * Extract calendar name from a value that might be a URL or a plain name.
     * Truncates to 255 characters to fit the database column.
     */
    private function extractCalendarName(string $value): string
    {
        // If empty, return a default name
        if (empty($value)) {
            return 'Calendar';
        }

        // Truncate to 255 characters to fit the database column
        return mb_substr($value, 0, 255);
    }
}
