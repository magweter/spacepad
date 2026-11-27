<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EventSource;
use App\Helpers\DisplaySettings;
use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Support\PortalEvents;
use App\Support\PortalShell;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every display of the workspace in one list, with a side panel per display (deep-linkable with
 * ?display=<id>) that shows its room today, its calendar, profile and paired tablets.
 *
 * Room status comes from the events the tablets already put in the cache, the same way as the
 * overview, so opening this page never calls a calendar provider.
 */
class DisplaysPageController extends Controller
{
    /** A tablet that has not been heard from in this many minutes counts as offline. */
    private const ONLINE_MINUTES = 10;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        $hasPro = $workspace->hasPro();

        PortalEvents::reset();
        $displays = Display::where('workspace_id', $workspace->id)
            ->with(['calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount', 'calendar.room', 'settings', 'profile'])
            ->orderBy('name')
            ->get();
        $devices = Device::where('workspace_id', $workspace->id)->whereNotNull('display_id')->get()->groupBy('display_id');

        $profiles = $hasPro
            ? DisplayProfile::where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name'])
            : collect();

        return Inertia::render('Displays/Index', [
            'shell' => PortalShell::for($request, $workspace, 'displays'),
            'displays' => $displays->map(fn (Display $display) => $this->display($display, $devices->get($display->id, collect())))->values()->all(),
            'selected' => $request->query('display'),
            'profiles' => $profiles->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            'hasPro' => $hasPro,
            'canCreate' => $user->can('create', Display::class),
            'mustUpgrade' => $user->shouldUpgradeForCurrentWorkspace(),
            // Checkboxes show as soon as there is more than one display, even before a profile
            // exists, so people discover that displays can be configured together.
            'canBulkAssign' => $hasPro && $displays->count() > 1,
            // The how-to is onboarding: once a profile is linked anywhere it has done its job.
            'showBulkHint' => ! $displays->contains(fn (Display $d) => $d->display_profile_id !== null),
            'connectCode' => $displays->isNotEmpty() ? $workspace->getConnectCode($user) : null,
            'urls' => [
                'newDisplay' => $displays->isEmpty() ? route('setup.first-display') : route('setup.first-display', ['step' => 'room', 'new' => 1]),
                'pairing' => route('setup.first-display', ['step' => 'tablet']),
                'profiles' => route('profiles.index'),
                'profilesCreate' => route('profiles.create'),
                'bulk' => route('displays.profile.bulk'),
                'calendars' => route('calendars.index'),
                'upgrade' => route('billing.index'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function display(Display $display, Collection $devices): array
    {
        $devices = $devices->sortByDesc('last_activity_at')->values();
        $latest = $devices->first();
        $cached = PortalEvents::for($display);
        $grace = $cached !== null && $display->isCheckInEnabled() ? $display->getCheckInGracePeriod() : null;
        $device = fn (Device $d) => [
            'name' => $d->name,
            'online' => $d->last_activity_at?->gt(now()->subMinutes(self::ONLINE_MINUTES)) ?? false,
            'lastSeen' => $d->last_activity_at?->diffForHumans(),
        ];

        return [
            'id' => $display->id,
            'name' => $display->name,
            'displayName' => $display->display_name,
            'status' => $display->status?->value,
            'statusLabel' => $display->status?->label(),
            'calendar' => $this->calendar($display->calendar),
            'profile' => $display->profile ? [
                'name' => $display->profile->name,
                'deviates' => DisplaySettings::deviatesFromProfile($display),
            ] : null,
            'hasOwnSettings' => $display->settings->isNotEmpty(),
            'lastSync' => $display->last_sync_at?->diffForHumans(),
            // The most recently heard-from tablet drives the room status, like on the overview.
            'device' => $latest ? $device($latest) : null,
            'devices' => $devices->map($device)->all(),
            'hasData' => $cached !== null,
            // Sent in UTC: the browser places them on its own day.
            'events' => collect($cached ?? [])
                ->filter(fn ($e) => $e->end?->gt(now()->subDay()) && $e->start?->lt(now()->addDay()))
                ->map(fn ($e) => [
                    'start' => $e->start->toIso8601String(),
                    'end' => $e->end->toIso8601String(),
                    'title' => $e->summary ?: 'Booked',
                    'tablet' => $e->calendar_id !== null || $e->source === EventSource::CUSTOM,
                    'checkInUntil' => $grace !== null && $e->checked_in_at === null ? $e->start->copy()->addMinutes($grace)->toIso8601String() : null,
                ])
                ->sortBy('start')->values()->all(),
            'urls' => [
                'configure' => route('displays.configure', $display),
                'status' => route('displays.updateStatus', $display),
                'makeActive' => route('displays.makeActive', $display),
                'delete' => route('displays.delete', $display),
                'diagnostics' => route('displays.diagnostics.run', $display),
                'resetAccount' => route('displays.diagnostics.reset-account', $display),
            ],
        ];
    }

    /**
     * The account the display reads from and which room or calendar of it.
     *
     * @return array<string, string|null>|null
     */
    private function calendar(?Calendar $calendar): ?array
    {
        if ($calendar === null) {
            return null;
        }

        [$provider, $account] = match (true) {
            $calendar->outlookAccount !== null => ['Microsoft 365', $calendar->outlookAccount],
            $calendar->googleAccount !== null => ['Google Workspace', $calendar->googleAccount],
            $calendar->caldavAccount !== null => ['CalDAV', $calendar->caldavAccount],
            default => [null, null],
        };

        $detail = match (true) {
            $calendar->room !== null => $calendar->room->name,
            filled($calendar->name) && ! $calendar->is_primary => $calendar->name,
            default => $account?->email,
        };

        return [
            'provider' => $provider,
            'account' => $account?->name ?? $account?->email,
            'detail' => $detail,
            'isRoom' => $calendar->room !== null,
        ];
    }
}
