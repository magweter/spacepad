<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\PortalEvents;
use App\Support\PortalRooms;
use App\Support\PortalShell;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live view: every room on a timeline of the day (or as cards), refreshed every minute. Meetings
 * can be booked, checked in, extended and ended from here, and other days looked up.
 */
class LiveViewController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        PortalEvents::reset();

        return Inertia::render('LiveView/Index', [
            'shell' => PortalShell::for($request, $workspace, 'live'),
            'rooms' => PortalRooms::forWorkspace($workspace->id),
            // Opened from the overview's "Book a room": start with the booking window.
            'book' => $request->boolean('book'),
            'urls' => [
                'day' => route('schedule.day'),
                'newDisplay' => $workspace->displays()->exists()
                    ? route('setup.first-display', ['step' => 'room', 'new' => 1])
                    : route('setup.first-display'),
            ],
        ]);
    }
}
