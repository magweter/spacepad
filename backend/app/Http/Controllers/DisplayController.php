<?php

namespace App\Http\Controllers;

use App\Enums\Provider;
use App\Enums\DisplayStatus;
use App\Events\UserOnboarded;
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

class DisplayController extends Controller
{
    public function __construct(protected OutlookService $outlookService)
    {
    }

    public function create(Request $request): View|Application|Factory
    {
        $outlookAccounts = auth()->user()->outlookAccounts;
        $displays = auth()->user()->displays;

        return view('pages.displays.create', [
            'outlookAccounts' => $outlookAccounts,
            'displays' => $displays
        ]);
    }

    /**
     * @throws \Exception
     */
    public function store(Request $request): RedirectResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string',
            'displayName' => 'required|string',
            'account' => 'required|string',
            'room' => 'required|string',
        ]);

        $display = DB::transaction(function () use ($validatedData) {
            $roomData = explode(',', $validatedData['room']);
            $outlookAccount = OutlookAccount::findOrFail($validatedData['account']);
            $outlookCalendar = $this->outlookService->fetchCalendarByEmail($outlookAccount, $roomData[0]);

            $calendar = Calendar::firstOrCreate([
                'calendar_id' => $outlookCalendar['id'],
            ], [
                'user_id' => auth()->id(),
                'outlook_account_id' => $validatedData['account'],
                'calendar_id' => $outlookCalendar['id'],
                'name' => $roomData[1],
            ]);

            Room::firstOrCreate([
                'email_address' => $roomData[0],
            ], [
                'user_id' => auth()->id(),
                'calendar_id' => $calendar->id,
                'email_address' => $roomData[0],
                'name' => $roomData[1],
            ]);

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
            'Display created! It will take a few minutes for the calendars to get synced.' :
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

        return redirect()->route('dashboard')->with('status', 'Display status has been changed.');
    }

    public function delete(Display $display): RedirectResponse
    {
        $this->authorize('delete', $display);

        $display->eventSubscriptions()->delete();
        $display->delete();

        return redirect()->route('dashboard')->with('status', 'Display has successfully been deleted.');
    }
}
