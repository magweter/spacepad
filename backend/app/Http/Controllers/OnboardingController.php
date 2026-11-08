<?php

namespace App\Http\Controllers;

use App\Enums\CalendarLocation;
use App\Enums\FunnelStep;
use App\Enums\OnboardingQuestion;
use App\Enums\PermissionType;
use App\Enums\PreviousSolution;
use App\Enums\Product;
use App\Enums\Role;
use App\Enums\RoomsEstimate;
use App\Enums\UsageType;
use App\Events\UserRegistered;
use App\Models\CalDAVAccount;
use App\Models\Device;
use App\Models\FunnelEvent;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CalDAVService;
use App\Services\Funnel;
use App\Services\GoogleService;
use App\Services\OnboardingAnswers;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The onboarding wizard: workspace, about you, products, calendar, room, tablet, ready.
 *
 * Every step saves on its own and can be skipped. The wizard opens at the first step that is
 * not done yet, so leaving halfway (for the calendar's OAuth screen, or for the night) picks
 * up where it was left.
 */
class OnboardingController extends Controller
{
    public const STEPS = ['workspace', 'about', 'products', 'calendar', 'room', 'tablet', 'ready'];

    /**
     * Steps that are only about the organisation, left out for personal use.
     */
    private const BUSINESS_STEPS = ['about', 'products'];

    private const SOURCE = 'onboarding';

    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);

        // A magic-link signup is verified the first time it lands here.
        if (! $user->hasVerifiedEmail() && ! $user->microsoft_id && ! $user->google_id) {
            $user->update(['email_verified_at' => now()]);
            event(new UserRegistered($user));
        }

        $steps = $this->stepsFor($user);
        $requested = $request->query('step');
        $step = in_array($requested, $steps, true) ? $requested : $this->firstOpenStep($user, $workspace, $steps);

        $display = $workspace->displays()->oldest()->first();
        $device = Device::where('workspace_id', $workspace->id)->latest()->first();

        return Inertia::render('Onboarding/Wizard', [
            'step' => $step,
            'steps' => $steps,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'usageType' => $user->usage_type?->value,
                'termsAccepted' => $user->terms_accepted_at !== null,
            ],
            'workspace' => [
                'name' => $workspace->name,
                'canRename' => $user->can('update', $workspace),
            ],
            'answers' => [
                'role' => OnboardingAnswers::latest($workspace, OnboardingQuestion::ROLE),
                'previous_solution' => OnboardingAnswers::latest($workspace, OnboardingQuestion::PREVIOUS_SOLUTION),
                'previous_solution_text' => $workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)?->text,
                'rooms' => OnboardingAnswers::latest($workspace, OnboardingQuestion::ROOMS),
                'calendar_provider' => OnboardingAnswers::latest($workspace, OnboardingQuestion::CALENDAR_LOCATION),
                'products' => OnboardingAnswers::latest($workspace, OnboardingQuestion::PRODUCTS),
            ],
            'options' => [
                'role' => OnboardingAnswers::options(OnboardingQuestion::ROLE),
                'previous_solution' => OnboardingAnswers::options(OnboardingQuestion::PREVIOUS_SOLUTION),
                'rooms' => OnboardingAnswers::options(OnboardingQuestion::ROOMS),
                'calendar_provider' => OnboardingAnswers::options(OnboardingQuestion::CALENDAR_LOCATION),
                'products' => OnboardingAnswers::options(OnboardingQuestion::PRODUCTS),
            ],
            'accounts' => $this->accounts($workspace),
            'display' => $display ? ['id' => $display->id, 'name' => $display->display_name ?: $display->name] : null,
            'canCreateDisplay' => ! $user->shouldUpgradeForCurrentWorkspace(),
            'device' => $device ? ['name' => $device->name] : null,
            'connectCode' => $display ? $workspace->getConnectCode($user) : null,
            'urls' => [
                'workspace' => route('onboarding.workspace'),
                'about' => route('onboarding.about'),
                'products' => route('onboarding.products'),
                'events' => route('onboarding.events'),
                'resources' => route('onboarding.resources'),
                'deviceStatus' => route('onboarding.device-status'),
                'complete' => route('onboarding.complete'),
                'skip' => route('onboarding.skip'),
                'logout' => route('logout'),
                'outlookAuth' => route('outlook-accounts.auth'),
                'googleAuth' => route('google-accounts.auth'),
                'caldavCreate' => route('caldav-accounts.create', ['onboarding' => 1]),
                'displaysStore' => route('displays.store'),
                'step' => route('onboarding'),
            ],
        ]);
    }

    public function workspace(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $selfHosted = config('settings.is_self_hosted');

        $data = $request->validate([
            'usage_type' => ['required', Rule::enum(UsageType::class)],
            'name' => ['required', 'string', 'max:255'],
            'workspace_name' => ['nullable', 'string', 'max:255'],
            'accept_terms' => [$selfHosted && ! $user->terms_accepted_at ? 'accepted' : 'nullable'],
        ], [
            'usage_type.required' => 'Pick how you will use Spacepad.',
            'name.required' => 'Enter your name.',
            'accept_terms.accepted' => 'Accept the terms to continue.',
        ]);

        $user->update(array_filter([
            'usage_type' => $data['usage_type'],
            'name' => $data['name'],
            'terms_accepted_at' => $selfHosted && ! $user->terms_accepted_at ? now() : null,
        ]));

        if (filled($data['workspace_name'] ?? null) && $user->can('update', $workspace)) {
            $workspace->update(['name' => $data['workspace_name']]);
        }

        $this->stepEvent(FunnelStep::ONBOARDING_STEP_COMPLETED, 'workspace', $workspace, $user);

        return $this->next($user->fresh(), 'workspace');
    }

    public function about(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $skipped = $request->boolean('skip');

        $data = $skipped ? [] : $request->validate([
            'role' => ['nullable', Rule::enum(Role::class)],
            'previous_solution' => ['nullable', Rule::enum(PreviousSolution::class)],
            'previous_solution_text' => ['nullable', 'required_if:previous_solution,other', 'string', 'max:255'],
            'rooms' => ['nullable', Rule::enum(RoomsEstimate::class)],
            'calendar_provider' => ['nullable', Rule::enum(CalendarLocation::class)],
        ], [
            'previous_solution_text.required_if' => 'Enter the name of the product.',
        ]);

        foreach ([
            OnboardingQuestion::ROLE->value => OnboardingQuestion::ROLE,
            OnboardingQuestion::PREVIOUS_SOLUTION->value => OnboardingQuestion::PREVIOUS_SOLUTION,
            OnboardingQuestion::ROOMS->value => OnboardingQuestion::ROOMS,
            OnboardingQuestion::CALENDAR_LOCATION->value => OnboardingQuestion::CALENDAR_LOCATION,
        ] as $field => $question) {
            OnboardingAnswers::store($workspace, $user, $question, $data[$field] ?? null, $data['previous_solution_text'] ?? null, self::SOURCE);
        }

        $this->stepEvent($skipped ? FunnelStep::ONBOARDING_STEP_SKIPPED : FunnelStep::ONBOARDING_STEP_COMPLETED, 'about', $workspace, $user);

        return $this->next($user, 'about');
    }

    public function products(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $skipped = $request->boolean('skip');

        $data = $skipped ? [] : $request->validate([
            'products' => ['array'],
            'products.*' => [Rule::enum(Product::class)],
        ]);

        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::PRODUCTS, $data['products'] ?? [], null, self::SOURCE);
        $this->stepEvent($skipped ? FunnelStep::ONBOARDING_STEP_SKIPPED : FunnelStep::ONBOARDING_STEP_COMPLETED, 'products', $workspace, $user);

        return $this->next($user, 'products');
    }

    /**
     * Step events for the steps that do their work elsewhere (calendar, room, tablet, ready),
     * and "viewed" for every step. A view is recorded once per step and workspace.
     */
    public function events(Request $request): HttpResponse
    {
        $data = $request->validate([
            'step' => ['required', Rule::in(self::STEPS)],
            'event' => ['required', Rule::in(['viewed', 'completed', 'skipped'])],
        ]);

        $workspace = $this->currentWorkspace($request);
        $event = match ($data['event']) {
            'viewed' => FunnelStep::ONBOARDING_STEP_VIEWED,
            'completed' => FunnelStep::ONBOARDING_STEP_COMPLETED,
            'skipped' => FunnelStep::ONBOARDING_STEP_SKIPPED,
        };

        $this->stepEvent($event, $data['step'], $workspace, $request->user());

        return response()->noContent();
    }

    /**
     * Rooms and calendars of a connected account, for picking the first display.
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
            // A personal account cannot list rooms: that is not an error, the calendars remain.
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
     * Whether a tablet has paired with this workspace, polled by the tablet step.
     */
    public function deviceStatus(Request $request): JsonResponse
    {
        $device = Device::where('workspace_id', $this->currentWorkspace($request)->id)->latest()->first();

        return response()->json([
            'paired' => $device !== null,
            'name' => $device?->name,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);

        if (! $user->onboarding_completed_at) {
            $user->update(['onboarding_completed_at' => now()]);
            Funnel::recordOnce(FunnelStep::ONBOARDING_COMPLETED, $workspace, $user);
        }

        return redirect()->route('onboarding.done');
    }

    public function done(Request $request): Response|RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $display = $workspace->displays()->oldest()->first();
        $device = Device::where('workspace_id', $workspace->id)->latest()->first();

        return Inertia::render('Onboarding/Done', [
            'display' => $display ? ['name' => $display->display_name ?: $display->name] : null,
            'device' => $device ? ['name' => $device->name] : null,
            'provider' => $this->accounts($workspace)[0]['providerLabel'] ?? null,
            'hasPro' => $workspace->hasPro(),
            'urls' => [
                'dashboard' => route('dashboard'),
                'addDisplay' => route('displays.create'),
                'team' => route('workspaces.members'),
                'boards' => route('boards.create'),
            ],
        ]);
    }

    public function skip(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'skipped_onboarding_at' => now(),
            'onboarding_completed_at' => $user->onboarding_completed_at ?? now(),
        ]);
        Funnel::recordOnce(FunnelStep::ONBOARDING_SKIPPED, $this->currentWorkspace($request), $user, ['step' => $request->input('step')]);

        return redirect()->route('dashboard');
    }

    /**
     * Kept for the old onboarding page's forms, which may still be open in a tab.
     */
    public function updateUsageType(Request $request): RedirectResponse
    {
        $request->validate(['usage_type' => 'required|in:business,personal']);
        $request->user()->update(['usage_type' => $request->usage_type]);

        return redirect()->route('onboarding');
    }

    public function acceptTerms(Request $request): RedirectResponse
    {
        $request->user()->update(['terms_accepted_at' => now()]);

        return redirect()->route('onboarding');
    }

    /**
     * @return array<int, string>
     */
    private function stepsFor(User $user): array
    {
        if ($user->usage_type === UsageType::PERSONAL) {
            return array_values(array_diff(self::STEPS, self::BUSINESS_STEPS));
        }

        return self::STEPS;
    }

    /**
     * @param  array<int, string>  $steps
     */
    private function firstOpenStep(User $user, Workspace $workspace, array $steps): string
    {
        $done = [
            'workspace' => $user->usage_type !== null,
            'about' => $workspace->latestAnswer(OnboardingQuestion::ROLE) !== null,
            'products' => $workspace->latestAnswer(OnboardingQuestion::PRODUCTS) !== null,
            'calendar' => $this->accounts($workspace) !== [],
            'room' => $workspace->displays()->exists(),
            'tablet' => Device::where('workspace_id', $workspace->id)->exists(),
        ];

        foreach ($steps as $step) {
            if (! ($done[$step] ?? false)) {
                return $step;
            }
        }

        return 'ready';
    }

    private function next(User $user, string $current): RedirectResponse
    {
        $steps = $this->stepsFor($user);
        $index = array_search($current, $steps, true);
        $next = $steps[$index + 1] ?? 'ready';

        return redirect()->route('onboarding', ['step' => $next]);
    }

    private function stepEvent(FunnelStep $event, string $step, Workspace $workspace, User $user): void
    {
        if ($event === FunnelStep::ONBOARDING_STEP_VIEWED) {
            $seen = FunnelEvent::query()
                ->where('workspace_id', $workspace->id)
                ->where('event', $event->value)
                ->where('properties->step', $step)
                ->exists();

            if ($seen) {
                return;
            }
        }

        Funnel::record($event, $workspace, $user, ['step' => $step]);
    }

    /**
     * Calendar accounts connected in this workspace, newest first.
     *
     * @return array<int, array{id: string, provider: string, providerLabel: string, name: string|null, email: string|null}>
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
