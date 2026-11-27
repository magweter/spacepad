<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Display;
use Illuminate\Support\Collection;

/**
 * A room as the portal shows it live (the overview's key figures and the Live view): its tablet,
 * today's events from the cache and what the signed-in user may do with them.
 *
 * Events come from the cache the displays already fill, so this never calls a calendar provider.
 * Call PortalEvents::reset() once per request before reading rooms.
 */
final class PortalRooms
{
    /** A tablet that has not been heard from in this many minutes counts as offline. */
    public const ONLINE_MINUTES = 10;

    /**
     * Every display of a workspace as a room, sorted by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forWorkspace(string $workspaceId): array
    {
        $displays = Display::where('workspace_id', $workspaceId)->with('calendar')->orderBy('name')->get();
        $devices = Device::where('workspace_id', $workspaceId)->get();

        return $displays->map(fn (Display $display) => self::room($display, $devices))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function room(Display $display, Collection $devices): array
    {
        $device = $devices->filter(fn (Device $d) => $d->display_id === $display->id)->sortByDesc('last_activity_at')->first();
        $cached = PortalEvents::for($display);
        $grace = $cached !== null && $display->isCheckInEnabled() ? $display->getCheckInGracePeriod() : null;

        return [
            'id' => $display->id,
            'name' => $display->display_name ?: $display->name,
            'status' => $display->status?->value,
            'device' => $device ? [
                'name' => $device->name,
                'online' => $device->last_activity_at?->gt(now()->subMinutes(self::ONLINE_MINUTES)) ?? false,
                'lastSeen' => $device->last_activity_at?->diffForHumans(),
            ] : null,
            'hasData' => $cached !== null,
            'error' => $cached === null && PortalEvents::failed($display) ? 'the calendar could not be read' : null,
            // Sent in UTC: the browser places them on its own day.
            'events' => collect($cached ?? [])
                ->filter(fn ($e) => $e->end?->gt(now()->subDay()) && $e->start?->lt(now()->addDay()))
                ->map(fn ($e) => PortalSchedule::event($e, $grace, auth()->user()))
                ->sortBy('start')->values()->all(),
            'url' => route('displays.configure', $display),
            'actions' => PortalSchedule::actions($display, auth()->user()),
            'urls' => [
                'book' => route('schedule.book', $display),
                // Base for an event's actions: {events}/{id}/check-in, /extend, and DELETE {events}/{id}.
                'events' => url("/schedule/{$display->id}/events"),
            ],
        ];
    }
}
