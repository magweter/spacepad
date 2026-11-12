<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\FunnelStep;
use App\Enums\OnboardingQuestion;
use App\Enums\PreviousSolution;
use App\Enums\RoomLocations;
use App\Enums\RoomsEstimate;
use App\Enums\Tablets;
use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Events\UserRegistered;
use App\Models\FunnelEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceJoinRequest;
use App\Notifications\WorkspaceJoinRequested;
use App\Services\ColleagueWorkspace;
use App\Services\Funnel;
use App\Services\OnboardingAnswers;
use App\Support\EmailDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Onboarding: four quick questions, then a choice of how to start.
 *
 * Workspace, your rooms, tablets and features, start. Setting up a display is not part of it:
 * it is its own flow (FirstDisplayController), offered at the end and from the portal. Every
 * question can be left open, and the wizard resumes at the first step that is not answered.
 */
class OnboardingController extends Controller
{
    public const STEPS = ['workspace', 'rooms', 'tablets', 'start'];

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

        if ($join = $this->joinPrompt($user, $workspace)) {
            return Inertia::render('Onboarding/JoinExisting', $join);
        }

        $requested = $request->query('step');
        $step = in_array($requested, self::STEPS, true) ? $requested : $this->firstOpenStep($user, $workspace);

        return Inertia::render('Onboarding/Wizard', [
            'step' => $step,
            'steps' => self::STEPS,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'usageType' => $user->usage_type?->value,
                'termsAccepted' => $user->terms_accepted_at !== null,
            ],
            'workspace' => [
                'name' => $this->suggestedName($user, $workspace),
                'canRename' => $user->can('update', $workspace),
            ],
            'answers' => [
                'previous_solution' => OnboardingAnswers::latest($workspace, OnboardingQuestion::PREVIOUS_SOLUTION),
                'previous_solution_text' => $workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)?->text,
                'rooms' => OnboardingAnswers::latest($workspace, OnboardingQuestion::ROOMS),
                'locations' => OnboardingAnswers::latest($workspace, OnboardingQuestion::LOCATIONS),
                'tablets' => OnboardingAnswers::latest($workspace, OnboardingQuestion::TABLETS),
                'features' => OnboardingAnswers::latest($workspace, OnboardingQuestion::FEATURES),
                'hardware_guide' => OnboardingAnswers::latest($workspace, OnboardingQuestion::HARDWARE_GUIDE),
            ],
            'options' => [
                'previous_solution' => OnboardingAnswers::options(OnboardingQuestion::PREVIOUS_SOLUTION),
                'rooms' => OnboardingAnswers::options(OnboardingQuestion::ROOMS),
                'locations' => OnboardingAnswers::options(OnboardingQuestion::LOCATIONS),
                'tablets' => OnboardingAnswers::options(OnboardingQuestion::TABLETS),
                'features' => OnboardingAnswers::options(OnboardingQuestion::FEATURES),
            ],
            'urls' => [
                'step' => route('onboarding'),
                'workspace' => route('onboarding.workspace'),
                'rooms' => route('onboarding.rooms'),
                'tablets' => route('onboarding.tablets'),
                'start' => route('onboarding.start'),
                'events' => route('onboarding.events'),
                'skip' => route('onboarding.skip'),
                'logout' => route('logout'),
                'invitationBase' => url('/invitations'),
                'license' => 'https://github.com/magweter/spacepad/blob/main/LICENSE.md',
            ],
        ]);
    }

    public function workspace(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $selfHosted = config('settings.is_self_hosted');

        // On a self-hosted instance the licence terms cannot be skipped.
        if ($selfHosted && ! $user->terms_accepted_at) {
            $request->merge(['skip' => false]);
        }

        if ($request->boolean('skip')) {
            $user->update(['usage_type' => $user->usage_type ?? UsageType::BUSINESS]);
            $this->stepEvent(FunnelStep::ONBOARDING_STEP_SKIPPED, 'workspace', $workspace, $user);

            return redirect()->route('onboarding', ['step' => 'rooms']);
        }

        $data = $request->validate([
            'usage_type' => ['required', Rule::enum(UsageType::class)],
            'workspace_name' => ['nullable', 'string', 'max:255'],
            'accept_terms' => [$selfHosted && ! $user->terms_accepted_at ? 'accepted' : 'nullable'],
        ], [
            'usage_type.required' => 'Pick how you will use Spacepad.',
            'accept_terms.accepted' => 'Accept the licence terms to continue.',
        ]);

        $user->update(array_filter([
            'usage_type' => $data['usage_type'],
            'terms_accepted_at' => $selfHosted && ! $user->terms_accepted_at ? now() : null,
        ]));

        if (filled($data['workspace_name'] ?? null) && $user->can('update', $workspace)) {
            $workspace->update(['name' => $data['workspace_name']]);
        }

        $this->stepEvent(FunnelStep::ONBOARDING_STEP_COMPLETED, 'workspace', $workspace, $user);

        return redirect()->route('onboarding', ['step' => 'rooms']);
    }

    public function rooms(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $skipped = $request->boolean('skip');

        $data = $skipped ? [] : $request->validate([
            'previous_solution' => ['nullable', Rule::enum(PreviousSolution::class)],
            'previous_solution_text' => ['nullable', 'required_if:previous_solution,other', 'string', 'max:255'],
            'rooms' => ['nullable', Rule::enum(RoomsEstimate::class)],
            'locations' => ['nullable', Rule::enum(RoomLocations::class)],
        ], [
            'previous_solution_text.required_if' => 'Enter the name of the product.',
        ]);

        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::PREVIOUS_SOLUTION, $data['previous_solution'] ?? null, $data['previous_solution_text'] ?? null, self::SOURCE);
        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::ROOMS, $data['rooms'] ?? null, null, self::SOURCE);
        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::LOCATIONS, $data['locations'] ?? null, null, self::SOURCE);

        $this->stepEvent($skipped ? FunnelStep::ONBOARDING_STEP_SKIPPED : FunnelStep::ONBOARDING_STEP_COMPLETED, 'rooms', $workspace, $user);

        return redirect()->route('onboarding', ['step' => 'tablets']);
    }

    public function tablets(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $skipped = $request->boolean('skip');

        $data = $skipped ? [] : $request->validate([
            'tablets' => ['nullable', Rule::enum(Tablets::class)],
            'features' => ['array'],
            'features.*' => [Rule::enum(Feature::class)],
            'hardware_guide' => ['nullable', 'boolean'],
        ]);

        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::TABLETS, $data['tablets'] ?? null, null, self::SOURCE);
        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::FEATURES, $data['features'] ?? [], null, self::SOURCE);

        // Only asked when there are no tablets yet: whether we may send the hardware guide.
        if (($data['tablets'] ?? null) === Tablets::NONE->value) {
            OnboardingAnswers::store($workspace, $user, OnboardingQuestion::HARDWARE_GUIDE, ! empty($data['hardware_guide']) ? 'yes' : 'no', null, self::SOURCE);
        }

        $this->stepEvent($skipped ? FunnelStep::ONBOARDING_STEP_SKIPPED : FunnelStep::ONBOARDING_STEP_COMPLETED, 'tablets', $workspace, $user);

        return redirect()->route('onboarding', ['step' => 'start']);
    }

    /**
     * The last step: set up the first display now, or look around first. Either way onboarding
     * is done; the first display flow is open from the portal at any time.
     */
    public function start(Request $request): RedirectResponse|SymfonyResponse
    {
        $data = $request->validate([
            'choice' => ['required', Rule::in(['first_display', 'look_around'])],
        ]);

        $user = $request->user();
        $workspace = $this->currentWorkspace($request);

        $this->stepEvent(FunnelStep::ONBOARDING_STEP_COMPLETED, 'start', $workspace, $user);
        $this->finish($user, $workspace, ['start' => $data['choice']]);

        return $data['choice'] === 'first_display'
            ? redirect()->route('setup.first-display')
            : $this->toBlade($request, route('dashboard'));
    }

    /**
     * "Viewed" for every step, recorded once per step and workspace. The first display flow
     * reports here too, with its own step names.
     */
    public function events(Request $request): HttpResponse
    {
        $data = $request->validate([
            'step' => ['required', Rule::in([...self::STEPS, ...FirstDisplayController::STEPS])],
            'event' => ['required', Rule::in(['viewed', 'completed', 'skipped'])],
        ]);

        $event = match ($data['event']) {
            'viewed' => FunnelStep::ONBOARDING_STEP_VIEWED,
            'completed' => FunnelStep::ONBOARDING_STEP_COMPLETED,
            'skipped' => FunnelStep::ONBOARDING_STEP_SKIPPED,
        };

        $this->stepEvent($event, $data['step'], $this->currentWorkspace($request), $request->user());

        return response()->noContent();
    }

    public function skip(Request $request): RedirectResponse|SymfonyResponse
    {
        $user = $request->user();

        $user->update(['skipped_onboarding_at' => now()]);
        $this->finish($user, $this->currentWorkspace($request), ['step' => $request->input('step')], FunnelStep::ONBOARDING_SKIPPED);

        return $this->toBlade($request, route('dashboard'));
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
     * Ask the owners and admins of the colleagues' workspace to let this person in.
     */
    public function requestJoin(Request $request): RedirectResponse
    {
        $user = $request->user();
        $colleagues = ColleagueWorkspace::for($user);
        abort_unless($colleagues !== null, 404);

        $joinRequest = WorkspaceJoinRequest::firstOrCreate(
            ['workspace_id' => $colleagues->id, 'user_id' => $user->id],
            ['status' => WorkspaceJoinRequest::PENDING]
        );

        if ($joinRequest->wasRecentlyCreated) {
            $colleagues->members()
                ->wherePivotIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
                ->get()
                ->each(fn ($admin) => $admin->notify(new WorkspaceJoinRequested(
                    requesterName: $user->name,
                    requesterEmail: $user->email,
                    workspaceName: $colleagues->name,
                    reviewUrl: route('join-requests.show', $joinRequest),
                )));

            Funnel::record(FunnelStep::JOIN_REQUESTED, $this->currentWorkspace($request), $user, ['workspace' => $colleagues->id]);
        }

        return redirect()->route('onboarding');
    }

    /**
     * Set up a workspace of their own after all.
     */
    public function separateWorkspace(Request $request): RedirectResponse
    {
        Funnel::recordOnce(FunnelStep::JOIN_DECLINED_BY_USER, $this->currentWorkspace($request), $request->user());

        return redirect()->route('onboarding', ['step' => 'workspace']);
    }

    /**
     * The "your colleagues are already on Spacepad" screen, while it applies: a colleague
     * workspace exists and this person has not chosen a workspace of their own yet.
     *
     * @return array<string, mixed>|null
     */
    private function joinPrompt(User $user, Workspace $workspace): ?array
    {
        if (Funnel::reached(FunnelStep::JOIN_DECLINED_BY_USER, $workspace)) {
            return null;
        }

        $pending = WorkspaceJoinRequest::where('user_id', $user->id)->where('status', WorkspaceJoinRequest::PENDING)->latest()->first();
        $colleagues = $pending?->workspace ?? ($user->usage_type === null ? ColleagueWorkspace::for($user) : null);

        // Declined before: they get a workspace of their own, without asking again.
        if (! $colleagues || WorkspaceJoinRequest::where('user_id', $user->id)->where('workspace_id', $colleagues->id)->where('status', WorkspaceJoinRequest::DECLINED)->exists()) {
            return null;
        }

        $members = $colleagues->members()->orderBy('workspace_members.created_at')->get();
        $owner = $colleagues->owners()->first();

        return [
            'sent' => $pending !== null,
            'user' => ['name' => $user->name, 'email' => $user->email],
            'domain' => EmailDomain::of($user->email),
            'workspace' => [
                'name' => $colleagues->name,
                'plan' => $colleagues->hasPro() ? 'Pro workspace' : 'Free workspace',
                'displays' => $colleagues->displays()->count(),
                'createdBy' => $owner ? strtok($owner->name, ' ') : null,
                'createdAgo' => $colleagues->created_at?->diffForHumans(),
                'members' => $members->map(fn ($m) => ['name' => strtok($m->name, ' ')])->values()->all(),
            ],
            'urls' => [
                'request' => route('onboarding.join'),
                'separate' => route('onboarding.separate'),
                'logout' => route('logout'),
            ],
        ];
    }

    /**
     * A name to start from, in title case: the company for a company address, otherwise the
     * name the workspace got at sign-up.
     */
    private function suggestedName(User $user, Workspace $workspace): string
    {
        if (! str_ends_with($workspace->name, "'s Workspace")) {
            return $workspace->name;
        }

        return EmailDomain::companyName($user->email) ?? Str::title($workspace->name);
    }

    /**
     * Leave the Inertia pages for a Blade one with a full page visit; an Inertia visit would
     * show the Blade response in a modal.
     */
    private function toBlade(Request $request, string $url): RedirectResponse|SymfonyResponse
    {
        return $request->header('X-Inertia') ? Inertia::location($url) : redirect()->to($url);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function finish(User $user, Workspace $workspace, array $properties, FunnelStep $event = FunnelStep::ONBOARDING_COMPLETED): void
    {
        if ($user->onboarding_completed_at === null) {
            $user->update(['onboarding_completed_at' => now()]);
            Funnel::recordOnce($event, $workspace, $user, $properties);
        }
    }

    private function firstOpenStep(User $user, Workspace $workspace): string
    {
        if ($user->usage_type === null) {
            return 'workspace';
        }

        if ($workspace->latestAnswer(OnboardingQuestion::ROOMS) === null) {
            return 'rooms';
        }

        if ($workspace->latestAnswer(OnboardingQuestion::TABLETS) === null) {
            return 'tablets';
        }

        return 'start';
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

    private function currentWorkspace(Request $request): Workspace
    {
        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        return $workspace;
    }
}
