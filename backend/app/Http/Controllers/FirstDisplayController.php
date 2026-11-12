<?php

namespace App\Http\Controllers;

use App\Enums\FunnelStep;
use App\Enums\PermissionType;
use App\Models\CalDAVAccount;
use App\Models\Device;
use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Workspace;
use App\Notifications\ItAdminCalendarRequest;
use App\Services\CalDAVService;
use App\Services\Funnel;
use App\Services\GoogleService;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Setting up a display: calendar, room, tablet, done. Four steps that can be left at any point.
 *
 * Reached from the end of onboarding and from the portal. The calendar connection runs through
 * the existing OAuth and CalDAV flows (they return here, see App\Support\OnboardingReturn) and
 * the display is created by DisplayController::store, so this controller only shows the steps.
 */
class FirstDisplayController extends Controller
{
    public const STEPS = ['calendar', 'room', 'tablet', 'done'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $accounts = $this->accounts($workspace);
        $display = $this->display($request, $workspace);
        $device = $display ? $this->device($workspace, $display) : null;

        $requested = $request->query('step');
        $step = in_array($requested, self::STEPS, true)
            ? $requested
            : ($accounts === [] ? 'calendar' : ($display === null ? 'room' : ($device === null ? 'tablet' : 'done')));

        return Inertia::render('FirstDisplay/Flow', [
            'step' => $step,
            'steps' => self::STEPS,
            'shell' => $this->shell($request, $workspace),
            'accounts' => $accounts,
            'display' => $display ? ['id' => $display->id, 'name' => $display->display_name ?: $display->name] : null,
            'device' => $device ? ['name' => $device->name] : null,
            'connectCode' => $display ? $workspace->getConnectCode($user) : null,
            'canCreateDisplay' => ! $user->shouldUpgradeForCurrentWorkspace(),
            'urls' => [
                'step' => route('setup.first-display'),
                'events' => route('onboarding.events'),
                'resources' => route('setup.first-display.resources'),
                'itRequest' => route('setup.first-display.it-request'),
                'deviceStatus' => route('setup.first-display.device-status', $display ? ['display' => $display->id] : []),
                'outlookAuth' => route('outlook-accounts.auth'),
                'googleAuth' => route('google-accounts.auth'),
                'caldavCreate' => route('caldav-accounts.create', ['onboarding' => 1]),
                'displaysStore' => route('displays.store'),
                'live' => route('setup.first-display.live', $display ? ['display' => $display->id] : []),
                'configure' => $display ? route('displays.configure', $display) : null,
                'dashboard' => route('dashboard'),
            ],
        ]);
    }

    /**
     * Rooms and calendars of a connected account, for picking the room.
     */
    public function resources(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['outlook', 'google', 'caldav'])],
            'account' => ['required', 'string'],
        ]);

        $account = match ($data['provider']) {
            'outlook' => OutlookAccount::findOrFail($data['account']),
            'google' => GoogleAccount::findOrFail($data['account']),
            'caldav' => CalDAVAccount::findOrFail($data['account']),
        };
        $this->authorize('view', $account);

        $rooms = [];
        $calendars = [];

        try {
            if ($data['provider'] === 'outlook') {
                $rooms = collect(app(OutlookService::class)->fetchRooms($account))
                    ->map(fn ($room) => ['id' => $room['emailAddress'], 'name' => $room['displayName'], 'detail' => $room['emailAddress']])->values()->all();
                $calendars = collect(app(OutlookService::class)->fetchCalendars($account))
                    ->map(fn ($calendar) => ['id' => $calendar['id'], 'name' => $calendar['name'], 'detail' => null])->values()->all();
            } elseif ($data['provider'] === 'google') {
                $rooms = collect(app(GoogleService::class)->fetchRooms($account))
                    ->map(fn ($room) => ['id' => $room->getResourceEmail(), 'name' => $room->getResourceName(), 'detail' => $room->getResourceEmail()])->values()->all();
                $calendars = collect(app(GoogleService::class)->fetchCalendars($account))
                    ->map(fn ($calendar) => ['id' => $calendar->getId(), 'name' => $calendar->getSummary(), 'detail' => null])->values()->all();
            } else {
                $calendars = collect(app(CalDAVService::class)->fetchCalendars($account))
                    ->map(fn ($calendar) => ['id' => $calendar['id'], 'name' => $calendar['name'], 'detail' => null])->values()->all();
            }
        } catch (Throwable $e) {
            // A personal account cannot list rooms: that is not an error, its calendars remain.
            report($e);

            if ($rooms === [] && $calendars === []) {
                return response()->json([
                    'rooms' => [],
                    'calendars' => [],
                    'error' => 'We could not load your rooms and calendars. Try again, or reconnect the account.',
                ]);
            }
        }

        return response()->json(['rooms' => $rooms, 'calendars' => $calendars, 'error' => null]);
    }

    /**
     * Whether a tablet has paired for this display, polled by the tablet step.
     */
    public function deviceStatus(Request $request): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);
        $display = $this->display($request, $workspace);
        $device = $display ? $this->device($workspace, $display) : null;

        return response()->json(['paired' => $device !== null, 'name' => $device?->name]);
    }

    /**
     * Mail an IT admin a request to connect the calendars, for someone without the rights.
     */
    public function itRequest(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']], [
            'email.required' => 'Enter the email address of your IT admin.',
        ]);

        $user = $request->user();
        $workspace = $this->currentWorkspace($request);

        $key = 'it-request:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'You sent a few requests already. Try again in an hour.');
        }
        RateLimiter::hit($key, 3600);

        Notification::route('mail', $data['email'])->notify(new ItAdminCalendarRequest(
            requesterName: $user->name,
            requesterEmail: $user->email,
            workspaceName: $workspace->name,
            microsoftConsentUrl: config('services.microsoft.enabled') ? app(OutlookService::class)->getAdminConsentUrl() : null,
            setupUrl: route('setup.first-display'),
        ));

        Funnel::record(FunnelStep::IT_REQUEST_SENT, $workspace, $user);

        return back()->with('success', "We emailed {$data['email']}. The rooms show up here once they have connected the calendar.");
    }

    /**
     * The "first display live" screen.
     */
    public function live(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        $display = $this->display($request, $workspace);
        $device = $display ? $this->device($workspace, $display) : null;
        $account = $this->accounts($workspace)[0] ?? null;

        return Inertia::render('FirstDisplay/Live', [
            'display' => $display ? ['name' => $display->display_name ?: $display->name] : null,
            'device' => $device ? ['name' => $device->name] : null,
            'provider' => $account['providerLabel'] ?? null,
            'hasPro' => $workspace->hasPro(),
            'urls' => [
                'dashboard' => route('dashboard'),
                'addDisplay' => route('setup.first-display', ['step' => 'room', 'new' => 1]),
                'team' => route('workspaces.members'),
                'boards' => route('boards.create'),
                'configure' => $display ? route('displays.configure', $display) : null,
            ],
        ]);
    }

    /**
     * The display this flow is about: the one just created (passed along in the URL), else the
     * workspace's first. "Add another display" starts without one.
     */
    private function display(Request $request, Workspace $workspace): ?Display
    {
        if ($request->boolean('new')) {
            return null;
        }

        if ($id = $request->query('display')) {
            return $workspace->displays()->whereKey($id)->first();
        }

        return $workspace->displays()->oldest()->first();
    }

    /**
     * A tablet showing this display, or one paired in this workspace since it was created.
     */
    private function device(Workspace $workspace, Display $display): ?Device
    {
        return Device::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($query) => $query->where('display_id', $display->id)->orWhere('created_at', '>=', $display->created_at))
            ->latest()
            ->first();
    }

    /**
     * What the portal shell around the flow shows: the workspace, its plan and who is signed in.
     *
     * @return array<string, mixed>
     */
    private function shell(Request $request, Workspace $workspace): array
    {
        $user = $request->user();
        $role = $workspace->members()->whereKey($user->id)->first()?->pivot?->role;

        return [
            'workspace' => $workspace->name,
            'plan' => $workspace->hasPro() ? 'Pro workspace' : 'Free workspace',
            'user' => $user->name,
            'role' => $role ? ucfirst(is_string($role) ? $role : $role->value) : null,
            'logout' => route('logout'),
            'nav' => [
                ['label' => 'Overview', 'href' => route('dashboard'), 'active' => false],
                ['label' => 'Displays', 'href' => route('dashboard'), 'active' => true],
                ['label' => 'Boards', 'href' => route('dashboard', ['tab' => 'boards']), 'active' => false],
                ['label' => 'Profiles', 'href' => route('dashboard', ['tab' => 'profiles']), 'active' => false],
                ['label' => 'Team', 'href' => route('workspaces.members'), 'active' => false, 'divider' => true],
                ['label' => 'Account', 'href' => route('profile.show'), 'active' => false],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(Workspace $workspace): array
    {
        $map = fn ($provider, $label) => fn ($account) => [
            'id' => $account->id,
            'provider' => $provider,
            'providerLabel' => $label,
            'name' => $account->name,
            'email' => $account->email ?? $account->username ?? null,
            'readOnly' => ($account->permission_type ?? null) === PermissionType::READ,
        ];

        return collect()
            ->merge(OutlookAccount::where('workspace_id', $workspace->id)->latest()->get()->map($map('outlook', 'Microsoft 365')))
            ->merge(GoogleAccount::where('workspace_id', $workspace->id)->latest()->get()->map($map('google', 'Google Workspace')))
            ->merge(CalDAVAccount::where('workspace_id', $workspace->id)->latest()->get()->map($map('caldav', 'CalDAV')))
            ->values()
            ->all();
    }

    private function currentWorkspace(Request $request): Workspace
    {
        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        return $workspace;
    }
}
