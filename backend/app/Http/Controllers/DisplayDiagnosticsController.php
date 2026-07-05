<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\DisplayStatus;
use App\Enums\GoogleBookingMethod;
use App\Enums\OutlookBookingMethod;
use App\Models\Display;
use App\Services\CalDAVService;
use App\Services\EventService;
use App\Services\GoogleService;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        $steps = [];
        $calendar = $display->calendar;

        // ── Step 1: Calendar configuration ────────────────────────────────────
        if (! $calendar) {
            $steps[] = $this->step(1, 'Calendar configuration', 'error',
                'No calendar linked to this display. Go to the display setup and connect a calendar.',
                []
            );

            return response()->json(['steps' => $steps]);
        }

        $provider = match (true) {
            (bool) $calendar->outlook_account_id => 'Microsoft 365 / Outlook',
            (bool) $calendar->google_account_id => 'Google Calendar',
            (bool) $calendar->caldav_account_id => 'CalDAV',
            default => 'Unknown',
        };

        $steps[] = $this->step(1, 'Calendar configuration', 'ok',
            "Connected to $provider".($calendar->room ? ' (room resource)' : ' (user calendar)'),
            [
                'Provider' => $provider,
                'Calendar ID' => $calendar->calendar_id,
                'Type' => $calendar->room ? 'Room resource — fetches by room email' : 'User calendar — fetches by calendar ID',
                'Room email' => $calendar->room?->email_address ?? '—',
            ]
        );

        // ── Step 2: Account authentication ────────────────────────────────────
        $account = $calendar->outlookAccount ?? $calendar->googleAccount ?? $calendar->caldavAccount ?? null;

        if (! $account) {
            $steps[] = $this->step(2, 'Account authentication', 'error',
                'Account record not found in database.', []
            );

            return response()->json(['steps' => $steps]);
        }

        $tokenExpiresAt = $account->token_expires_at ?? null;
        $tokenExpired = $tokenExpiresAt ? now()->gt($tokenExpiresAt) : true;
        $hasRefreshToken = ! empty($account->refresh_token);
        $accountStatus = $account->status?->value ?? (string) $account->status ?? 'unknown';
        $accountEmail = $account->email ?? '—';

        $authStatus = (! $tokenExpired || $hasRefreshToken) ? 'ok' : 'error';
        $authMessage = $tokenExpired
            ? ($hasRefreshToken ? 'Access token expired — will be refreshed automatically on next request' : 'Access token expired and no refresh token available')
            : 'Access token is valid';

        $steps[] = $this->step(2, 'Account authentication', $authStatus, $authMessage, [
            'Account email' => $accountEmail,
            'Account status' => $accountStatus,
            'Token expires at' => $tokenExpiresAt ? $tokenExpiresAt->toDateTimeString().' (UTC)' : '—',
            'Token expired' => $tokenExpired ? 'Yes' : 'No',
            'Has refresh token' => $hasRefreshToken ? 'Yes' : 'No',
        ]);

        if ($authStatus === 'error') {
            return response()->json(['steps' => $steps]);
        }

        // ── Step 3: Permission & booking capability check ─────────────────────
        $permType = $account->permission_type ?? null;
        $permValue = $permType instanceof \App\Enums\PermissionType ? $permType->value : (string) $permType;
        $hasWrite = in_array($permValue, ['write', 'read_write'], true);
        $needsWrite = $display->isBookingEnabled();

        $outlookBookingMethod = null;
        $outlookMethodValue = null;
        $googleBookingMethod = null;
        $googleMethodValue = null;

        $permDetails = [
            'Granted permission level' => ucfirst($permValue ?: 'unknown'),
            'Booking enabled on display' => $needsWrite ? 'Yes' : 'No',
        ];

        if ($calendar->outlook_account_id) {
            $permDetails['OAuth scopes'] = $hasWrite
                ? 'Calendars.ReadWrite.Shared'
                : 'Calendars.Read.Shared';

            $outlookBookingMethod = $account->booking_method instanceof OutlookBookingMethod
                ? $account->booking_method
                : null;
            $outlookMethodValue = $outlookBookingMethod?->value;

            if ($hasWrite) {
                $permDetails['Booking method'] = $outlookBookingMethod
                    ? $outlookBookingMethod->label()
                    : 'Not set';
            }
        }

        if ($calendar->google_account_id) {
            $permDetails['OAuth scopes'] = $hasWrite
                ? 'calendar.events + calendar.readonly'
                : 'calendar.events.readonly + calendar.readonly';

            $googleBookingMethod = $account->booking_method instanceof GoogleBookingMethod
                ? $account->booking_method
                : null;
            $googleMethodValue = $googleBookingMethod?->value;

            if ($hasWrite) {
                $permDetails['Booking method'] = $googleBookingMethod
                    ? $googleBookingMethod->label()
                    : ($account->isBusiness() ? 'Not set' : 'User account (personal)');
            }
        }

        // Determine the specific permission/booking status and actionable message
        $permStatus = 'ok';
        $permMessage = '';
        $fixNote = null;

        if (! $hasWrite && $needsWrite) {
            // Booking enabled but account is read-only
            $permStatus = 'warning';
            $permMessage = 'Booking is enabled on this display but the account has read-only access — bookings made from the tablet will fail.';
            $fixNote = 'Go to Accounts, disconnect this account, and reconnect it with "Read & Write" permission.';

        } elseif (! $hasWrite) {
            // Read-only, booking not needed
            $permMessage = 'Read-only access — events are displayed on the tablet. Bookings from the tablet are not enabled for this display.';

        } elseif ($needsWrite && $calendar->google_account_id && $account->isBusiness() && ! $googleMethodValue) {
            // Write + Google Workspace but no booking method configured
            $permStatus = 'warning';
            $permMessage = 'Write access granted but no booking method is configured — room bookings will fail until a method is selected.';
            $fixNote = 'Go to Accounts, click the calendar icon next to this account, and choose a booking method.';

        } elseif ($needsWrite && $calendar->google_account_id && $googleMethodValue === 'service_account' && empty($account->service_account_file_path)) {
            // Service account selected but file not uploaded
            $permStatus = 'error';
            $permMessage = 'Booking method is set to "Service account" but the service account JSON file has not been uploaded — bookings will fail.';
            $fixNote = 'Go to Accounts, click the calendar icon next to this account, and upload the service account JSON file.';

        } elseif ($needsWrite && $calendar->outlook_account_id && $account->isBusiness() && ! $outlookMethodValue) {
            // Write + Microsoft business but no booking method
            $permStatus = 'warning';
            $permMessage = 'Write access granted but no booking method is configured — room bookings will fail until a method is selected.';
            $fixNote = 'Go to Accounts, click the calendar icon next to this account, and choose a booking method.';

        } elseif ($needsWrite && $calendar->outlook_account_id && ($outlookMethodValue ?? null) === 'admin_consent') {
            // Admin consent method — note that admin must have approved
            $permMessage = 'Booking method is set to "Admin consent" — room bookings use app-level permissions. Ensure your M365 tenant admin has completed the one-time consent step.';

        } elseif ($needsWrite) {
            $permMessage = 'Write access granted — bookings from the tablet are supported.';

        } else {
            $permMessage = 'Read & Write access is granted. Booking from the tablet is currently disabled in display settings.';
        }

        if ($fixNote) {
            $permDetails['How to fix'] = $fixNote;
        }

        $steps[] = $this->step(3, 'Calendar permissions', $permStatus, $permMessage, $permDetails);

        // ── Step 4: Raw API fetch ──────────────────────────────────────────────
        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $rawNorm = [];   // normalized for display
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
                        'title' => $e['subject'] ?? '(no title)',
                        'start' => $e['start']['dateTime'] ?? '—',
                        'end' => $e['end']['dateTime'] ?? '—',
                        'all_day' => ($e['isAllDay'] ?? false) ? 'Yes' : 'No',
                        'status' => '—',
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
                        'title' => $e->getSummary() ?? '(no title)',
                        'start' => $e->getStart()->getDateTime() ?? $e->getStart()->getDate() ?? '—',
                        'end' => $e->getEnd()->getDateTime() ?? $e->getEnd()->getDate() ?? '—',
                        'all_day' => ($e->getStart()->getDate() !== null) ? 'Yes' : 'No',
                        'status' => $e->getStatus() ?? '—',
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
                        'title' => $e['summary'] ?? '(no title)',
                        'start' => $e['start'] ?? '—',
                        'end' => $e['end'] ?? '—',
                        'all_day' => ($e['isAllDay'] ?? false) ? 'Yes' : 'No',
                        'status' => '—',
                    ];
                }
            }
        } catch (\Exception $e) {
            $fetchError = $e->getMessage();
        }

        if ($fetchError) {
            $steps[] = $this->step(4, 'Fetch events from calendar API', 'error',
                'API call failed: '.$fetchError,
                ['Error' => $fetchError]
            );

            return response()->json(['steps' => $steps]);
        }

        $rawCount = count($rawNorm);
        $allDayCount = count(array_filter($rawNorm, fn ($e) => $e['all_day'] === 'Yes'));

        $rawMessage = $rawCount === 0
            ? 'No events returned by the calendar API for today'
            : "$rawCount event(s) returned by the API ($allDayCount all-day will be filtered)";

        $steps[] = $this->step(4, 'Fetch events from calendar API',
            $rawCount === 0 ? 'warning' : 'ok',
            $rawMessage,
            [
                'Time window' => $start->toDateTimeString().' → '.$end->toDateTimeString().' (UTC)',
                'Total events returned' => $rawCount,
                'All-day events (always filtered)' => $allDayCount,
                'Timed events' => $rawCount - $allDayCount,
                'events' => array_slice($rawNorm, 0, 10),
            ]
        );

        if ($rawCount === 0) {
            $steps[] = $this->step(5, 'Apply server-side filters', 'warning',
                'Nothing to filter — no events came from the API.',
                ['Note' => 'No timed events were returned so no filtering was applied.']
            );
            $steps[] = $this->step(6, 'Events delivered to tablet', 'warning',
                'Tablet receives 0 events for today.',
                ['count' => 0, 'events' => []]
            );
            $this->appendSubscriptionStep($steps, $display);

            return response()->json(['steps' => $steps]);
        }

        // ── Steps 5 + 6: Let EventService run the full pipeline (no cache) ─────
        try {
            $deliveredEvents = $this->eventService->getEventsForDisplay($display->id, Carbon::today());
        } catch (\Exception $e) {
            $steps[] = $this->step(5, 'Apply server-side filters', 'error',
                'Processing pipeline failed: '.$e->getMessage(),
                ['Error' => $e->getMessage()]
            );

            return response()->json(['steps' => $steps]);
        }

        $deliveredCount = $deliveredEvents->count();
        $timedRaw = $rawCount - $allDayCount;
        $filteredOut = $timedRaw - $deliveredCount;

        $filterDetails = [
            'All-day removed' => $allDayCount,
            'Released (missed check-in) removed' => max(0, $filteredOut),
            'Remaining after filters' => $deliveredCount,
        ];

        $steps[] = $this->step(5, 'Apply server-side filters',
            $deliveredCount > 0 || $timedRaw === 0 ? 'ok' : 'warning',
            "$timedRaw timed event(s) in → $deliveredCount event(s) out",
            $filterDetails
        );

        $deliveredPreview = $deliveredEvents->take(10)->map(fn ($e) => [
            'title' => $e->summary ?? '(no title)',
            'start' => $e->start?->toDateTimeString() ?? '—',
            'end' => $e->end?->toDateTimeString() ?? '—',
        ])->values()->toArray();

        $cacheKey = $display->getEventsCacheKey();
        $isCached = Cache::has($cacheKey);
        $subsCount = $display->eventSubscriptions->count();

        $steps[] = $this->step(6, 'Events delivered to tablet',
            $deliveredCount > 0 ? 'ok' : 'warning',
            $deliveredCount.' event(s) delivered to the tablet for today',
            [
                'count' => $deliveredCount,
                'Cache active' => $isCached ? 'Yes — tablet may see up to 15 min stale data' : 'No — tablet always fetches live',
                'Webhook subscriptions' => $subsCount,
                'events' => $deliveredPreview,
            ]
        );

        // ── Step 6: Webhook subscriptions ─────────────────────────────────────
        $this->appendSubscriptionStep($steps, $display);

        return response()->json(['steps' => $steps]);
    }

    /**
     * Reset the linked calendar account's status back to "connected" so the next
     * request attempts a fresh token instead of short-circuiting on the error
     * state. Used from the diagnostics ("troubleshoot") modal when an account is
     * stuck in a permanent error state.
     */
    public function resetAccount(Display $display): JsonResponse
    {
        $this->authorize('update', $display);

        $display->load(['calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount']);
        $calendar = $display->calendar;
        $account = $calendar?->outlookAccount ?? $calendar?->googleAccount ?? $calendar?->caldavAccount;

        if (! $account) {
            return response()->json([
                'ok' => false,
                'message' => 'No calendar account is linked to this display.',
            ], 422);
        }

        $previousStatus = $account->status?->value ?? (string) $account->status;

        // Reset the account and (if parked) the display together so a failure updating
        // the display rolls back the account change rather than leaving a mismatched state.
        DB::transaction(function () use ($account, $display) {
            $account->update(['status' => AccountStatus::CONNECTED]);

            // If the display itself was parked in an error state, bring it back so it
            // can resume once the account reconnects.
            if ($display->status === DisplayStatus::ERROR) {
                $display->update(['status' => DisplayStatus::ACTIVE]);
            }
        });

        logger()->info('Account status reset from diagnostics', [
            'display_id' => $display->id,
            'account_type' => class_basename($account),
            'account_id' => $account->id,
            'previous_status' => $previousStatus,
            'reset_by' => auth()->id(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Account status reset to connected — re-running to attempt a fresh token.',
        ]);
    }

    private function appendSubscriptionStep(array &$steps, Display $display): void
    {
        $subs = $display->eventSubscriptions;
        $subsCount = $subs->count();

        $subData = $subs->map(fn ($s) => [
            'ID' => $s->subscription_id,
            'Expires at' => Carbon::parse($s->expiration)->toDateTimeString().' (UTC)',
            'Expired' => Carbon::parse($s->expiration)->isPast() ? 'Yes ⚠' : 'No',
            'Resource' => $s->resource,
        ])->toArray();

        $anyExpired = collect($subData)->contains(fn ($s) => str_contains($s['Expired'], '⚠'));
        $subStatus = $subsCount === 0 ? 'warning' : ($anyExpired ? 'warning' : 'ok');

        $steps[] = $this->step(7, 'Webhook / real-time updates',
            $subStatus,
            $subsCount === 0
                ? 'No webhook registered — calendar changes won\'t push instantly, but the tablet still polls every 60 s'
                : "$subsCount subscription(s) active — real-time push enabled",
            $subsCount === 0
                ? [
                    'Active subscriptions' => 0,
                    'Real-time push' => 'Disabled',
                    'Device polling' => 'Every 60 seconds — all events stay up to date',
                    'Impact on display' => 'None — the display works normally without webhooks',
                ]
                : array_merge(
                    ['Active subscriptions' => $subsCount],
                    ...array_map(fn ($s, $i) => ['Subscription '.($i + 1) => "{$s['ID']} · expires {$s['Expires at']}".($s['Expired'] !== 'No' ? ' ⚠ EXPIRED' : '')], $subData, array_keys($subData))
                )
        );
    }

    private function step(int $number, string $title, string $status, string $message, array $data): array
    {
        return compact('number', 'title', 'status', 'message', 'data');
    }
}
