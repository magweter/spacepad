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
use App\Events\UserRegistered;
use App\Models\FunnelEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Funnel;
use App\Services\OnboardingAnswers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
                'name' => $workspace->name,
                'canRename' => $user->can('update', $workspace),
            ],
            'answers' => [
                'previous_solution' => OnboardingAnswers::latest($workspace, OnboardingQuestion::PREVIOUS_SOLUTION),
                'previous_solution_text' => $workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)?->text,
                'rooms' => OnboardingAnswers::latest($workspace, OnboardingQuestion::ROOMS),
                'locations' => OnboardingAnswers::latest($workspace, OnboardingQuestion::LOCATIONS),
                'tablets' => OnboardingAnswers::latest($workspace, OnboardingQuestion::TABLETS),
                'features' => OnboardingAnswers::latest($workspace, OnboardingQuestion::FEATURES),
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
        ]);

        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::TABLETS, $data['tablets'] ?? null, null, self::SOURCE);
        OnboardingAnswers::store($workspace, $user, OnboardingQuestion::FEATURES, $data['features'] ?? [], null, self::SOURCE);

        $this->stepEvent($skipped ? FunnelStep::ONBOARDING_STEP_SKIPPED : FunnelStep::ONBOARDING_STEP_COMPLETED, 'tablets', $workspace, $user);

        return redirect()->route('onboarding', ['step' => 'start']);
    }

    /**
     * The last step: set up the first display now, or look around first. Either way onboarding
     * is done; the first display flow is open from the portal at any time.
     */
    public function start(Request $request): RedirectResponse
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
            : redirect()->route('dashboard');
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

    public function skip(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update(['skipped_onboarding_at' => now()]);
        $this->finish($user, $this->currentWorkspace($request), ['step' => $request->input('step')], FunnelStep::ONBOARDING_SKIPPED);

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
