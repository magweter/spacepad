<?php

namespace App\Http\Controllers;

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Models\Workspace;
use App\Services\EventService;
use App\Support\LocalDay;
use App\Support\PortalEvents;
use App\Support\PortalSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The portal's day timeline beyond today, and managing meetings from it.
 *
 * Another day is fetched live from the calendars (only today is kept in the cache), so the page
 * shows a spinner while it loads. Booking, checking in, extending and ending go through the same
 * EventService calls as the tablet at the door, under the same rules: Pro, a display that is not
 * paused, booking switched on for booking, and the display's own extend and cancel settings
 * (EventService enforces those last two). Every member of the workspace may use them.
 */
class PortalScheduleController extends Controller
{
    /** Seconds one day request may spend asking calendars before the rest show as not loaded. */
    private const DAY_BUDGET_SECONDS = 20;

    /** Minutes from now a booking may start and still count as "now", for rooms without booking ahead. */
    private const BOOK_NOW_MINUTES = 5;

    public function __construct(private EventService $events) {}

    public function day(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'offset' => ['required', 'integer', 'between:-840,840'],
        ]);

        $workspace = $this->workspace($request);
        $date = Carbon::parse($data['date'])->startOfDay();
        $day = LocalDay::fromDateAndOffset($data['date'], (int) $data['offset']);
        $deadline = microtime(true) + self::DAY_BUDGET_SECONDS;

        $rooms = Display::where('workspace_id', $workspace->id)->orderBy('name')->get()
            ->map(function (Display $display) use ($date, $day, $deadline) {
                $usable = $display->calendar_id !== null
                    && ! in_array($display->status, [DisplayStatus::DEACTIVATED, DisplayStatus::REQUIRES_PRO], true);

                if (! $usable) {
                    return ['id' => $display->id, 'events' => null, 'error' => null];
                }
                if (microtime(true) > $deadline) {
                    return ['id' => $display->id, 'events' => [], 'error' => 'The calendar took too long. Try again.'];
                }

                try {
                    $grace = $display->isCheckInEnabled() ? $display->getCheckInGracePeriod() : null;
                    $events = $this->events->getEventsForDisplay($display->id, $date, $day)
                        ->map(fn ($e) => PortalSchedule::event($e, $grace))
                        ->sortBy('start')->values()->all();

                    return ['id' => $display->id, 'events' => $events, 'error' => null];
                } catch (Throwable $e) {
                    report($e);

                    return ['id' => $display->id, 'events' => [], 'error' => 'This calendar could not be read.'];
                }
            })->values()->all();

        return response()->json(['date' => $data['date'], 'rooms' => $rooms]);
    }

    public function book(Request $request, Display $display): JsonResponse
    {
        $this->authorizeRoom($request, $display, 'book');
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'summary' => ['nullable', 'string', 'max:255'],
        ]);

        // Like the tablet: without "allow future bookings" a room only takes a booking that starts now.
        if (! PortalSchedule::actions($display)['bookAhead'] && Carbon::parse($data['start'])->gt(now()->addMinutes(self::BOOK_NOW_MINUTES))) {
            return response()->json(['message' => 'This room only takes bookings that start now. Booking ahead is switched off in its settings.'], 422);
        }

        return $this->attempt($display, fn () => $this->events->bookRoom(
            displayId: $display->id,
            userId: $request->user()->id,
            summary: $data['summary'] ?? __('Reserved'),
            start: Carbon::parse($data['start'])->utc(),
            end: Carbon::parse($data['end'])->utc(),
        ), 'Booked.', 'The room could not be booked. It may already be taken at that time.');
    }

    public function checkIn(Request $request, Display $display, string $eventId): JsonResponse
    {
        $this->authorizeRoom($request, $display, 'checkIn');

        return $this->attempt($display, fn () => $this->events->checkInToEvent($eventId, $display->id), 'Checked in.', 'Could not check in to this meeting.');
    }

    public function extend(Request $request, Display $display, string $eventId): JsonResponse
    {
        $this->authorizeRoom($request, $display, 'extend');
        $data = $request->validate(['new_end' => ['required', 'date']]);

        return $this->attempt($display, fn () => $this->events->extendEvent($eventId, $display->id, Carbon::parse($data['new_end'])->utc()),
            'Extended.', 'The meeting could not be extended. The room may be booked right after it.');
    }

    public function cancel(Request $request, Display $display, string $eventId): JsonResponse
    {
        $this->authorizeRoom($request, $display, 'cancel');

        return $this->attempt($display, fn () => $this->events->cancelEvent($eventId, $display->id), 'Meeting cancelled.', 'This meeting could not be cancelled here.');
    }

    /**
     * Members of the display's workspace only, and only what the room allows (PortalSchedule::actions).
     */
    private function authorizeRoom(Request $request, Display $display, string $action): void
    {
        abort_unless($display->workspace?->hasMember($request->user()), 404);

        $allowed = PortalSchedule::actions($display)[$action];
        abort_if($allowed === false || $allowed === 'none', 403, match ($action) {
            'book' => 'Booking is not available for this room.',
            'extend' => 'Extending meetings is switched off for this room.',
            'checkIn' => 'Check-in is switched off for this room.',
            default => 'Ending meetings is switched off for this room.',
        });
    }

    private function attempt(Display $display, callable $action, string $done, string $failed): JsonResponse
    {
        try {
            $action();
            PortalEvents::forget($display);

            return response()->json(['message' => $done]);
        } catch (Throwable $e) {
            report($e);
            // The service's own refusals (a booked slot, "only tablet bookings") say what is wrong.
            $message = str_contains($e->getMessage(), 'not allowed') || str_contains($e->getMessage(), 'Only events')
                ? $e->getMessage() : $failed;

            return response()->json(['message' => $message], 422);
        }
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        return $workspace;
    }
}
