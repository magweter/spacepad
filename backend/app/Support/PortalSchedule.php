<?php

namespace App\Support;

use App\Enums\DisplayStatus;
use App\Helpers\DisplaySettings;
use App\Models\Display;
use App\Models\Event;

/**
 * How the portal's day timeline sees a room and its meetings, shared by the overview (today, from
 * the cache) and the day endpoint (any day, fetched live). What a room allows follows the
 * display's own settings, the same rules the tablet at the door applies.
 */
class PortalSchedule
{
    /**
     * @return array<string, mixed>
     */
    public static function event(Event $e, ?int $grace): array
    {
        return [
            'id' => (string) $e->id,
            'start' => $e->start->toIso8601String(),
            'end' => $e->end->toIso8601String(),
            'title' => $e->summary ?: 'Booked',
            'tablet' => $e->isTabletBooking(),
            'checkInUntil' => $grace !== null && $e->checked_in_at === null ? $e->start->copy()->addMinutes($grace)->toIso8601String() : null,
            'organizer' => filled($e->organizer_name) ? $e->organizer_name : ($e->isTabletBooking() ? $e->user?->name : null),
            'location' => $e->location ?: null,
            'joinUrl' => $e->join_url ?: null,
            'checkedIn' => $e->checked_in_at !== null,
        ];
    }

    /**
     * What may be done with this room's meetings from the portal: nothing without Pro or on a
     * paused display, and otherwise what the display's settings allow the tablet.
     *
     * @return array<string, mixed>
     */
    public static function actions(Display $display): array
    {
        $usable = $display->workspace?->hasPro()
            && ! in_array($display->status, [DisplayStatus::DEACTIVATED, DisplayStatus::REQUIRES_PRO], true)
            && $display->calendar_id !== null;

        return [
            'book' => $usable && $display->isBookingEnabled(),
            'bookAhead' => $usable && $display->isBookingEnabled() && DisplaySettings::isFutureBookingEnabled($display),
            'extend' => $usable && DisplaySettings::isExtendEnabled($display),
            'checkIn' => $usable && $display->isCheckInEnabled(),
            // all | tablet_only | none, as on the tablet
            'cancel' => $usable ? DisplaySettings::getCancelPermission($display) : 'none',
        ];
    }
}
