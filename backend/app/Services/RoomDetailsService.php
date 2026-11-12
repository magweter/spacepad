<?php

namespace App\Services;

use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use Illuminate\Support\Facades\Cache;

/**
 * The floor and seat count a calendar provider keeps for a room, used to fill in a display's
 * location and seats so nobody has to type what the directory already knows.
 *
 * Google Workspace has them on its calendar resources (floorName, capacity), Microsoft 365 on
 * its room places (floorLabel, capacity). CalDAV has no such thing. Only a display connected
 * to a room has a match; a display on a personal calendar never does.
 *
 * The provider lists every room in one call, so the list is remembered per account for an hour:
 * the room picker already fetched it when the display was created, and the configure page
 * would otherwise ask again on every visit.
 */
class RoomDetailsService
{
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly GoogleService $googleService,
        private readonly OutlookService $outlookService,
    ) {}

    /**
     * Remember a room list the caller already fetched, keyed by room e-mail address.
     *
     * @param  iterable<mixed>  $rooms  Google CalendarResource objects or Microsoft Graph room arrays
     */
    public function remember(GoogleAccount|OutlookAccount $account, iterable $rooms): void
    {
        Cache::put($this->cacheKey($account), $this->index($account, $rooms), self::TTL_SECONDS);
    }

    /**
     * The location and seat count the provider has for the display's room, or null when the
     * display is not on a room, the provider keeps neither, or the provider cannot be reached.
     *
     * @param  bool  $fetch  Ask the provider when the list is not remembered. Off where a slow or
     *                       failing call must not hold up the request, like creating a display.
     * @return array{location: ?string, capacity: ?int}|null
     */
    public function forDisplay(Display $display, bool $fetch = true): ?array
    {
        $calendar = $display->calendar;
        $email = $calendar?->room?->email_address;
        $account = $calendar?->googleAccount ?? $calendar?->outlookAccount;

        if (! $email || ! $account) {
            return null;
        }

        $rooms = Cache::get($this->cacheKey($account));

        if ($rooms === null && $fetch) {
            try {
                $fetched = $account instanceof GoogleAccount
                    ? $this->googleService->fetchRooms($account)
                    : $this->outlookService->fetchRooms($account);
            } catch (\Throwable $e) {
                logger()->info('Could not fetch room details: '.$e->getMessage());

                return null;
            }

            $this->remember($account, $fetched ?? []);
            $rooms = Cache::get($this->cacheKey($account));
        }

        $details = $rooms[strtolower($email)] ?? null;

        return $details && ($details['location'] !== null || $details['capacity'] !== null) ? $details : null;
    }

    /**
     * Fill the display's location and seats from its room where they are still empty. A value
     * someone typed is never overwritten.
     */
    public function fillMissing(Display $display, bool $fetch = true): bool
    {
        if ($display->location !== null && $display->capacity !== null) {
            return false;
        }

        $details = $this->forDisplay($display, $fetch);
        if (! $details) {
            return false;
        }

        $changes = array_filter([
            'location' => $display->location === null ? $details['location'] : null,
            'capacity' => $display->capacity === null ? $details['capacity'] : null,
        ], fn ($value) => $value !== null);

        if (! $changes) {
            return false;
        }

        $display->update($changes);

        return true;
    }

    /**
     * @return array{location: ?string, capacity: ?int}
     */
    public static function fromGoogleResource(object $resource): array
    {
        return [
            'location' => self::floor($resource->getFloorName(), $resource->getFloorSection()),
            'capacity' => self::capacity($resource->getCapacity()),
        ];
    }

    /**
     * @return array{location: ?string, capacity: ?int}
     */
    public static function fromGraphRoom(array $room): array
    {
        $label = $room['floorLabel'] ?? null;
        if (($label === null || trim((string) $label) === '') && isset($room['floorNumber'])) {
            $label = (string) $room['floorNumber'];
        }

        return [
            'location' => self::floor($label),
            'capacity' => self::capacity($room['capacity'] ?? null),
        ];
    }

    /**
     * "2" becomes "Floor 2"; a name like "Ground floor" stays as it is. A section is added after
     * a comma, the way the display joins its subline.
     */
    private static function floor(mixed $name, mixed $section = null): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $location = preg_match('/^-?\d+$/', $name) ? "Floor {$name}" : $name;

        $section = trim((string) $section);
        if ($section !== '') {
            $location .= ', '.$section;
        }

        return mb_substr($location, 0, 64);
    }

    private static function capacity(mixed $capacity): ?int
    {
        $capacity = (int) $capacity;

        return $capacity >= 1 && $capacity <= 999 ? $capacity : null;
    }

    /**
     * @return array<string, array{location: ?string, capacity: ?int}>
     */
    private function index(GoogleAccount|OutlookAccount $account, iterable $rooms): array
    {
        $index = [];
        foreach ($rooms as $room) {
            if ($account instanceof GoogleAccount) {
                $email = $room->getResourceEmail();
                $details = self::fromGoogleResource($room);
            } else {
                $email = $room['emailAddress'] ?? null;
                $details = self::fromGraphRoom($room);
            }

            if ($email) {
                $index[strtolower($email)] = $details;
            }
        }

        return $index;
    }

    private function cacheKey(GoogleAccount|OutlookAccount $account): string
    {
        $provider = $account instanceof GoogleAccount ? 'google' : 'outlook';

        return "room-details:{$provider}:{$account->id}";
    }
}
