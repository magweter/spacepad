<?php

namespace App\Http\Controllers;

use App\Enums\CancellationReason;
use App\Enums\DisplayStatus;
use App\Enums\EventSource;
use App\Enums\FunnelStep;
use App\Enums\GoogleBookingMethod;
use App\Enums\OnboardingQuestion;
use App\Enums\PermissionType;
use App\Enums\PreviousSolution;
use App\Enums\RoomsEstimate;
use App\Models\CalDAVAccount;
use App\Models\Device;
use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Workspace;
use App\Services\EventService;
use App\Services\Funnel;
use App\Services\FunnelTracking;
use App\Support\PortalShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The portal's landing page: what blocks the product, the rooms right now, a few key figures and
 * what is left to set up.
 *
 * Room status comes from the events the displays already have in the cache (filled when a tablet
 * asks for them), so opening the overview never calls a calendar provider. A display whose tablet
 * has not asked yet shows as "no data yet". With caching off (local development) the events are
 * fetched on the spot instead.
 */
class OverviewController extends Controller
{
    /** A tablet that has not been heard from in this many minutes counts as offline. */
    private const ONLINE_MINUTES = 10;

    /** Flashes that open a modal on the calendars page. */
    private const CLASSIC_FLASHES = [
        'open-service-account-modal',
        'open-google-booking-method-modal',
        'open-microsoft-booking-method-modal',
        'needs_admin_consent',
    ];

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // Old links to a tab, and flows that end in a modal, still belong to the classic page.
        if ($request->filled('tab') || collect(self::CLASSIC_FLASHES)->contains(fn ($key) => $request->session()->has($key))) {
            $request->session()->reflash();

            // Old tab links go to their own page now; the modal flows end on the calendars page.
            return match ($request->query('tab')) {
                'boards' => redirect()->route('boards.index'),
                'profiles' => redirect()->route('profiles.index'),
                'displays' => redirect()->route('displays.index'),
                default => redirect()->route('calendars.index'),
            };
        }

        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        $displays = Display::where('workspace_id', $workspace->id)->with('calendar')->orderBy('name')->get();
        $devices = Device::where('workspace_id', $workspace->id)->get();
        $accounts = $this->accounts($workspace);
        $hasPro = $workspace->hasPro();

        if ($devices->isNotEmpty()) {
            FunnelTracking::devicePaired($workspace);
        }

        $askProfile = $workspace->shouldAskProfile($user);
        if ($askProfile) {
            Funnel::recordOnce(FunnelStep::PROFILE_CARD_SHOWN, $workspace, $user);
        }

        $cancellation = $user->can('manageBilling', $workspace) ? $workspace->pendingCancellationFeedback() : null;

        return Inertia::render('Overview/Index', [
            'shell' => PortalShell::for($request, $workspace, 'overview'),
            'notices' => $this->notices($request, $workspace, $displays),
            'rooms' => $displays->map(fn (Display $display) => $this->room($display, $devices))->values()->all(),
            'accounts' => $accounts,
            'checklist' => [
                'calendar' => $accounts !== [],
                'display' => $displays->isNotEmpty(),
                'tablet' => $devices->isNotEmpty(),
                'teammate' => $workspace->members()->count() > 1 || $workspace->invitations()->exists(),
                'board' => $workspace->boards()->exists(),
            ],
            'hasPro' => $hasPro,
            'connectCode' => $displays->isNotEmpty() ? $workspace->getConnectCode($user) : null,
            'profileCard' => $askProfile ? [
                'firstDisplay' => $displays->count() === 1,
                'rooms' => $workspace->latestAnswer(OnboardingQuestion::ROOMS)?->value,
                'roomOptions' => collect(RoomsEstimate::cases())->map(fn ($o) => ['value' => $o->value, 'label' => $o->label()])->all(),
                'solutionOptions' => collect(PreviousSolution::cases())->map(fn ($o) => ['value' => $o->value, 'label' => $o->label()])->all(),
                'urls' => ['update' => route('workspace.profile.update'), 'dismiss' => route('workspace.profile.dismiss')],
            ] : null,
            'cancellation' => $cancellation ? [
                'reasons' => collect(CancellationReason::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label(), 'prompt' => $r->detailsPrompt()])->all(),
                'urls' => [
                    'feedback' => route('billing.cancellations.feedback', $cancellation),
                    'dismiss' => route('billing.cancellations.dismiss', $cancellation),
                ],
            ] : null,
            'selfHosted' => config('settings.is_self_hosted') ? [
                'version' => config('settings.version', 'dev'),
                'appUrl' => config('app.url'),
            ] : null,
            'urls' => [
                'newDisplay' => $displays->isEmpty() ? route('setup.first-display') : route('setup.first-display', ['step' => 'room', 'new' => 1]),
                'displays' => route('displays.index'),
                'calendars' => route('calendars.index'),
                'team' => route('workspaces.members'),
                'boardsCreate' => route('boards.create'),
                'pairing' => route('setup.first-display', ['step' => 'tablet']),
            ],
        ]);
    }

    /**
     * Only what stops the product from working; nothing shows when nothing blocks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function notices(Request $request, Workspace $workspace, Collection $displays): array
    {
        $notices = [];
        $manage = fn (array $query) => route('calendars.index', $query);

        foreach (OutlookAccount::where('workspace_id', $workspace->id)->get() as $account) {
            if ($account->permission_type === PermissionType::WRITE && $account->isBusiness() && $account->booking_method === null) {
                $notices[] = [
                    'tone' => 'warning', 'tag' => 'Action needed',
                    'title' => "Choose how {$account->email} books rooms",
                    'body' => 'Booking from a display waits for this choice.',
                    'cta' => 'Choose', 'href' => $manage(['open' => 'microsoft-booking-method', 'account' => $account->id]),
                ];
            }
        }

        foreach (GoogleAccount::where('workspace_id', $workspace->id)->get() as $account) {
            if ($account->permission_type === PermissionType::WRITE && $account->isBusiness() && $account->booking_method === null) {
                $notices[] = [
                    'tone' => 'warning', 'tag' => 'Action needed',
                    'title' => "Choose how {$account->email} books rooms",
                    'body' => 'Booking from a display waits for this choice.',
                    'cta' => 'Choose', 'href' => $manage(['open' => 'google-booking-method', 'account' => $account->id]),
                ];
            } elseif ($account->isBusiness() && $account->booking_method === GoogleBookingMethod::SERVICE_ACCOUNT && ! $account->service_account_file_path) {
                $notices[] = [
                    'tone' => 'warning', 'tag' => 'Action needed',
                    'title' => "Upload the service account for {$account->email}",
                    'body' => 'Booking from a display needs the service account key.',
                    'cta' => 'Upload', 'href' => $manage(['open' => 'service-account', 'account' => $account->id]),
                ];
            }
        }

        $paused = $displays->filter(fn (Display $d) => $d->status === DisplayStatus::REQUIRES_PRO)->count();
        if ($paused > 0) {
            $notices[] = [
                'tone' => 'danger', 'tag' => 'Paused',
                'title' => $paused === 1 ? '1 display is paused' : "{$paused} displays are paused",
                'body' => 'The free plan shows one display. Upgrade to Pro to bring the others back.',
                'cta' => 'See Pro', 'href' => route('billing.index'), 'upgrade' => 'displays',
            ];
        }

        return $notices;
    }

    /**
     * @return array<string, mixed>
     */
    private function room(Display $display, Collection $devices): array
    {
        $device = $devices->filter(fn (Device $d) => $d->display_id === $display->id)->sortByDesc('last_activity_at')->first();
        $cached = $this->events($display);
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
            'url' => route('displays.configure', $display),
        ];
    }

    /**
     * The display's events as the tablets see them: from the cache, or fetched straight away when
     * caching is off (then there is no cache to read).
     */
    private function events(Display $display): ?Collection
    {
        if (config('services.events.cache_enabled')) {
            return Cache::get($display->getEventsCacheKey()) ?? Cache::get($display->getEventsCacheKey().':fallback');
        }

        if ($display->calendar_id === null || $display->status === DisplayStatus::REQUIRES_PRO) {
            return null;
        }

        try {
            return app(EventService::class)->getEventsForDisplay($display->id);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(Workspace $workspace): array
    {
        $map = fn (string $label) => fn ($account) => [
            'provider' => $label,
            'email' => $account->email ?? $account->username ?? $account->name,
            'readOnly' => ($account->permission_type ?? null) === PermissionType::READ,
        ];

        return collect()
            ->merge(OutlookAccount::where('workspace_id', $workspace->id)->get()->map($map('Microsoft 365')))
            ->merge(GoogleAccount::where('workspace_id', $workspace->id)->get()->map($map('Google Workspace')))
            ->merge(CalDAVAccount::where('workspace_id', $workspace->id)->get()->map($map('CalDAV')))
            ->values()->all();
    }
}
