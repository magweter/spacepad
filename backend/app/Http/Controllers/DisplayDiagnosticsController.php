<?php

namespace App\Http\Controllers;

use App\Models\Display;
use App\Services\EventService;
use App\Services\OutlookService;
use App\Services\GoogleService;
use App\Services\CalDAVService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DisplayDiagnosticsController extends Controller
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected CalDAVService $caldavService,
        protected EventService $eventService,
    ) {}

    public function run(Display $display): JsonResponse
    {
        $this->authorize('update', $display);

        $display->load(['calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount', 'calendar.room', 'eventSubscriptions']);

        $steps   = [];
        $calendar = $display->calendar;

        // ── Step 1: Calendar configuration ────────────────────────────────────
        if (!$calendar) {
            $steps[] = $this->step(1, 'Calendar configuration', 'error',
                'No calendar linked to this display. Go to the display setup and connect a calendar.',
                []
            );
            return response()->json(['steps' => $steps]);
        }

        $provider = match (true) {
            (bool) $calendar->outlook_account_id => 'Microsoft 365 / Outlook',
            (bool) $calendar->google_account_id  => 'Google Calendar',
            (bool) $calendar->caldav_account_id  => 'CalDAV',
            default                              => 'Unknown',
        };

        $steps[] = $this->step(1, 'Calendar configuration', 'ok',
            "Connected to $provider" . ($calendar->room ? ' (room resource)' : ' (user calendar)'),
            [
                'Provider'    => $provider,
                'Calendar ID' => $calendar->calendar_id,
                'Type'        => $calendar->room ? 'Room resource — fetches by room email' : 'User calendar — fetches by calendar ID',
                'Room email'  => $calendar->room?->email_address ?? '—',
            ]
        );

        // ── Step 2: Account authentication ────────────────────────────────────
        $account = $calendar->outlookAccount ?? $calendar->googleAccount ?? $calendar->caldavAccount ?? null;

        if (!$account) {
            $steps[] = $this->step(2, 'Account authentication', 'error',
                'Account record not found in database.', []
            );
            return response()->json(['steps' => $steps]);
        }

        $tokenExpiresAt  = $account->token_expires_at ?? null;
        $tokenExpired    = $tokenExpiresAt ? now()->gt($tokenExpiresAt) : true;
        $hasRefreshToken = !empty($account->refresh_token);
        $accountStatus   = $account->status?->value ?? (string) $account->status ?? 'unknown';
        $accountEmail    = $account->email ?? '—';

        $authStatus  = (!$tokenExpired || $hasRefreshToken) ? 'ok' : 'error';
        $authMessage = $tokenExpired
            ? ($hasRefreshToken ? 'Access token expired — will be refreshed automatically on next request' : 'Access token expired and no refresh token available')
            : 'Access token is valid';

        $steps[] = $this->step(2, 'Account authentication', $authStatus, $authMessage, [
            'Account email'      => $accountEmail,
            'Account status'     => $accountStatus,
            'Token expires at'   => $tokenExpiresAt?->toDateTimeString() . ' (UTC)' ?? '—',
            'Token expired'      => $tokenExpired ? 'Yes' : 'No',
            'Has refresh token'  => $hasRefreshToken ? 'Yes' : 'No',
        ]);

        if ($authStatus === 'error') {
            return response()->json(['steps' => $steps]);
        }

        // ── Step 3: Raw API fetch ──────────────────────────────────────────────
        $start      = now()->startOfDay();
        $end        = now()->endOfDay();
        $rawNorm    = [];   // normalized for display
        $fetchError = null;

        try {
            if ($calendar->outlook_account_id && $calendar->outlookAccount) {
                $raw = $calendar->room
                    ? $this->outlookService->fetchEventsByUser(
                        outlookAccount: $calendar->outlookAccount,
                        emailAddress: $calendar->calendar_id,
                        startDateTime: $start,
                        endDateTime: $end,
                    )
                    : $this->outlookService->fetchEventsByCalendar(
                        outlookAccount: $calendar->outlookAccount,
                        calendarId: $calendar->calendar_id,
                        startDateTime: $start,
                        endDateTime: $end,
                    );

                foreach ($raw as $e) {
                    $rawNorm[] = [
                        'title'   => $e['subject'] ?? '(no title)',
                        'start'   => $e['start']['dateTime'] ?? '—',
                        'end'     => $e['end']['dateTime'] ?? '—',
                        'all_day' => ($e['isAllDay'] ?? false) ? 'Yes' : 'No',
                        'status'  => '—',
                    ];
                }

            } elseif ($calendar->google_account_id && $calendar->googleAccount) {
                $googleEvents = $this->googleService->fetchEvents(
                    googleAccount: $calendar->googleAccount,
                    calendarId: $calendar->calendar_id,
                    startDateTime: $start,
                    endDateTime: $end,
                );
                foreach ($googleEvents as $e) {
                    $rawNorm[] = [
                        'title'   => $e->getSummary() ?? '(no title)',
                        'start'   => $e->getStart()->getDateTime() ?? $e->getStart()->getDate() ?? '—',
                        'end'     => $e->getEnd()->getDateTime() ?? $e->getEnd()->getDate() ?? '—',
                        'all_day' => ($e->getStart()->getDate() !== null) ? 'Yes' : 'No',
                        'status'  => $e->getStatus() ?? '—',
                    ];
                }

            } elseif ($calendar->caldav_account_id && $calendar->caldavAccount) {
                $caldavEvents = $this->caldavService->fetchEvents(
                    caldavAccount: $calendar->caldavAccount,
                    calendarId: $calendar->calendar_id,
                    startDateTime: $start,
                    endDateTime: $end,
                );
                foreach ($caldavEvents as $e) {
                    $rawNorm[] = [
                        'title'   => $e['summary'] ?? '(no title)',
                        'start'   => $e['start'] ?? '—',
                        'end'     => $e['end'] ?? '—',
                        'all_day' => ($e['isAllDay'] ?? false) ? 'Yes' : 'No',
                        'status'  => '—',
                    ];
                }
            }
        } catch (\Exception $e) {
            $fetchError = $e->getMessage();
        }

        if ($fetchError) {
            $steps[] = $this->step(3, 'Fetch events from calendar API', 'error',
                'API call failed: ' . $fetchError,
                ['Error' => $fetchError]
            );
            return response()->json(['steps' => $steps]);
        }

        $rawCount    = count($rawNorm);
        $allDayCount = count(array_filter($rawNorm, fn($e) => $e['all_day'] === 'Yes'));

        $rawMessage = $rawCount === 0
            ? 'No events returned by the calendar API for today'
            : "$rawCount event(s) returned by the API ($allDayCount all-day will be filtered)";

        $steps[] = $this->step(3, 'Fetch events from calendar API',
            $rawCount === 0 ? 'warning' : 'ok',
            $rawMessage,
            [
                'Time window'                       => $start->toDateTimeString() . ' → ' . $end->toDateTimeString() . ' (UTC)',
                'Total events returned'             => $rawCount,
                'All-day events (always filtered)'  => $allDayCount,
                'Timed events'                      => $rawCount - $allDayCount,
                'events'                            => array_slice($rawNorm, 0, 10),
            ]
        );

        if ($rawCount === 0) {
            $steps[] = $this->step(4, 'Apply server-side filters', 'warning',
                'Nothing to filter — no events came from the API.',
                []
            );
            $steps[] = $this->step(5, 'Events delivered to tablet', 'warning',
                'Tablet receives 0 events for today.',
                ['count' => 0, 'events' => []]
            );
            $this->appendSubscriptionStep($steps, $display);
            return response()->json(['steps' => $steps]);
        }

        // ── Step 4 + 5: Let EventService run the full pipeline (no cache) ──────
        try {
            $deliveredEvents = $this->eventService->getEventsForDisplay($display->id, Carbon::today());
        } catch (\Exception $e) {
            $steps[] = $this->step(4, 'Apply server-side filters', 'error',
                'Processing pipeline failed: ' . $e->getMessage(),
                ['Error' => $e->getMessage()]
            );
            return response()->json(['steps' => $steps]);
        }

        $deliveredCount  = $deliveredEvents->count();
        $timedRaw        = $rawCount - $allDayCount;
        $filteredOut     = $timedRaw - $deliveredCount;

        $filterDetails = [
            'All-day removed'                    => $allDayCount,
            'Released (missed check-in) removed' => max(0, $filteredOut),
            'Remaining after filters'            => $deliveredCount,
        ];

        $steps[] = $this->step(4, 'Apply server-side filters',
            $deliveredCount > 0 || $timedRaw === 0 ? 'ok' : 'warning',
            "$timedRaw timed event(s) in → $deliveredCount event(s) out",
            $filterDetails
        );

        $deliveredPreview = $deliveredEvents->take(10)->map(fn($e) => [
            'title' => $e->summary ?? '(no title)',
            'start' => $e->start?->toDateTimeString() ?? '—',
            'end'   => $e->end?->toDateTimeString() ?? '—',
        ])->values()->toArray();

        $cacheKey  = $display->getEventsCacheKey();
        $isCached  = Cache::has($cacheKey);
        $subsCount = $display->eventSubscriptions->count();

        $steps[] = $this->step(5, 'Events delivered to tablet',
            $deliveredCount > 0 ? 'ok' : 'warning',
            $deliveredCount . ' event(s) delivered to the tablet for today',
            [
                'count'                 => $deliveredCount,
                'Cache active'          => $isCached ? 'Yes — tablet may see up to 15 min stale data' : 'No — tablet always fetches live',
                'Webhook subscriptions' => $subsCount,
                'events'                => $deliveredPreview,
            ]
        );

        // ── Step 6: Webhook subscriptions ─────────────────────────────────────
        $this->appendSubscriptionStep($steps, $display);

        return response()->json(['steps' => $steps]);
    }

    private function appendSubscriptionStep(array &$steps, Display $display): void
    {
        $subs      = $display->eventSubscriptions;
        $subsCount = $subs->count();

        $subData = $subs->map(fn($s) => [
            'ID'         => $s->subscription_id,
            'Expires at' => Carbon::parse($s->expiration)->toDateTimeString() . ' (UTC)',
            'Expired'    => Carbon::parse($s->expiration)->isPast() ? 'Yes ⚠' : 'No',
            'Resource'   => $s->resource,
        ])->toArray();

        $anyExpired = collect($subData)->contains(fn($s) => str_contains($s['Expired'], '⚠'));
        $subStatus  = $subsCount === 0 ? 'warning' : ($anyExpired ? 'warning' : 'ok');

        $steps[] = $this->step(6, 'Webhook / real-time updates',
            $subStatus,
            $subsCount === 0
                ? 'No active webhook subscriptions — real-time push is off, display polls every 15 min via cache'
                : "$subsCount subscription(s) — real-time updates active",
            $subData
        );
    }

    private function step(int $number, string $title, string $status, string $message, array $data): array
    {
        return compact('number', 'title', 'status', 'message', 'data');
    }
}
