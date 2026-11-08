<?php

use App\Enums\OnboardingQuestion;
use App\Enums\UsageType;
use App\Models\Device;
use App\Models\Display;
use App\Models\FunnelEvent;
use App\Models\OnboardingAnswer;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Support\OnboardingReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

/**
 * The onboarding wizard: workspace, about you, products, calendar, room, tablet, ready. Every
 * step saves on its own, can be skipped, and the wizard resumes at the first open step.
 */
function newcomer(): User
{
    return User::factory()->create([
        'usage_type' => null,
        'email_verified_at' => now(),
        'terms_accepted_at' => now(),
    ]);
}

function wizardSteps(User $user): array
{
    return FunnelEvent::where('workspace_id', $user->primaryWorkspace()->id)
        ->whereIn('event', ['onboarding_step_completed', 'onboarding_step_skipped'])
        ->orderBy('id')
        ->get()
        ->map(fn ($e) => $e->event.':'.$e->properties['step'])
        ->all();
}

test('a newcomer lands on the first step of the wizard', function () {
    $user = newcomer();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding'));

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Wizard')
            ->where('step', 'workspace')
            ->where('steps', ['workspace', 'about', 'products', 'calendar', 'room', 'tablet', 'ready'])
            ->has('options.role', 4)
            ->has('options.rooms', 4));
});

test('the workspace step saves the name, usage and workspace name', function () {
    $user = newcomer();

    $this->actingAs($user)
        ->post(route('onboarding.workspace'), ['usage_type' => 'business', 'name' => 'Sanne de Vries', 'workspace_name' => 'Acme HQ'])
        ->assertRedirect(route('onboarding', ['step' => 'about']));

    $user->refresh();
    expect($user->name)->toBe('Sanne de Vries')
        ->and($user->usage_type)->toBe(UsageType::BUSINESS)
        ->and($user->primaryWorkspace()->name)->toBe('Acme HQ')
        ->and(wizardSteps($user))->toBe(['onboarding_step_completed:workspace']);
});

test('personal use skips the questions about the organisation', function () {
    $user = newcomer();

    $this->actingAs($user)
        ->post(route('onboarding.workspace'), ['usage_type' => 'personal', 'name' => 'Sam'])
        ->assertRedirect(route('onboarding', ['step' => 'calendar']));

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->where('steps', ['workspace', 'calendar', 'room', 'tablet', 'ready']));
});

test('a self-hosted instance asks to accept the terms', function () {
    config(['settings.is_self_hosted' => true]);
    $user = newcomer();
    $user->update(['terms_accepted_at' => null]);

    $this->actingAs($user)
        ->post(route('onboarding.workspace'), ['usage_type' => 'business', 'name' => 'Sam'])
        ->assertSessionHasErrors('accept_terms');

    $this->actingAs($user)
        ->post(route('onboarding.workspace'), ['usage_type' => 'business', 'name' => 'Sam', 'accept_terms' => true])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->terms_accepted_at)->not->toBeNull();
});

test('the about step stores every answer, and open questions as skipped', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);
    $workspace = $user->primaryWorkspace();

    $this->actingAs($user)
        ->post(route('onboarding.about'), ['role' => 'office_manager', 'previous_solution' => 'other', 'previous_solution_text' => 'Meetio', 'rooms' => '4-15'])
        ->assertRedirect(route('onboarding', ['step' => 'products']));

    $answers = OnboardingAnswer::where('workspace_id', $workspace->id)->pluck('value', 'question')->all();
    expect($answers)->toEqualCanonicalizing([
        'role' => 'office_manager',
        'previous_solution' => 'other',
        'rooms' => '4-15',
        'calendar_provider' => 'skipped',
    ])->and($answers['calendar_provider'])->toBe('skipped')
        ->and($workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)->text)->toBe('Meetio')
        ->and(OnboardingAnswer::first()->source)->toBe('onboarding');
});

test('something else needs a name', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)
        ->post(route('onboarding.about'), ['previous_solution' => 'other'])
        ->assertSessionHasErrors('previous_solution_text');
});

test('skipping a step stores every question as skipped', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)->post(route('onboarding.about'), ['skip' => true]);

    expect(OnboardingAnswer::pluck('value')->unique()->all())->toBe(['skipped'])
        ->and(wizardSteps($user))->toBe(['onboarding_step_skipped:about']);
});

test('several products are one answer of several rows', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)
        ->post(route('onboarding.products'), ['products' => ['display', 'board']])
        ->assertRedirect(route('onboarding', ['step' => 'calendar']));

    expect(OnboardingAnswer::where('question', 'products')->pluck('value')->all())->toEqualCanonicalizing(['display', 'board']);
});

test('the wizard resumes at the first step that is not done', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);
    $workspace = $user->primaryWorkspace();
    $this->actingAs($user)->post(route('onboarding.about'), ['skip' => true]);
    $this->actingAs($user)->post(route('onboarding.products'), ['skip' => true]);
    OutlookAccount::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->where('step', 'room')->has('accounts', 1));
});

test('connecting a calendar from the wizard returns to the room step', function () {
    $user = newcomer();

    $this->actingAs($user)
        ->post(route('outlook-accounts.auth'), ['permission_type' => 'read', 'onboarding' => 1]);

    expect(session('onboarding_return'))->toBeTrue()
        ->and(OnboardingReturn::redirect('room')->getTargetUrl())->toBe(route('onboarding', ['step' => 'room']))
        ->and(OnboardingReturn::redirect('room')->getTargetUrl())->toBe(route('dashboard'));
});

test('creating the display from the wizard continues with the tablet', function () {
    $user = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS, 'onboarding_completed_at' => null]);
    $account = OutlookAccount::where('user_id', $user->id)->first();

    $this->actingAs($user)
        ->post(route('displays.store'), [
            'name' => 'Boardroom',
            'displayName' => 'Boardroom',
            'provider' => 'outlook',
            'account' => $account->id,
            'room' => 'boardroom@example.com,Boardroom',
            'from_onboarding' => 1,
        ])
        ->assertRedirect(route('onboarding', ['step' => 'tablet']));

    expect(Display::count())->toBe(1)
        ->and(wizardSteps($user))->toBe(['onboarding_step_completed:room']);
});

test('the tablet step sees a paired tablet', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    $this->actingAs($user)->getJson(route('onboarding.device-status'))->assertJson(['paired' => false]);

    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'name' => 'Lobby iPad']);

    $this->actingAs($user)->getJson(route('onboarding.device-status'))->assertJson(['paired' => true, 'name' => 'Lobby iPad']);
});

test('a step is recorded as viewed once', function () {
    $user = newcomer();

    $this->actingAs($user)->postJson(route('onboarding.events'), ['step' => 'calendar', 'event' => 'viewed'])->assertNoContent();
    $this->actingAs($user)->postJson(route('onboarding.events'), ['step' => 'calendar', 'event' => 'viewed'])->assertNoContent();
    $this->actingAs($user)->postJson(route('onboarding.events'), ['step' => 'nowhere', 'event' => 'viewed'])->assertUnprocessable();

    expect(FunnelEvent::where('event', 'onboarding_step_viewed')->count())->toBe(1);
});

test('finishing ends the wizard and shows the done screen', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)->post(route('onboarding.complete'))->assertRedirect(route('onboarding.done'));

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and(FunnelEvent::where('event', 'onboarding_completed')->count())->toBe(1);

    $this->actingAs($user)->get(route('onboarding.done'))
        ->assertInertia(fn (Assert $page) => $page->component('Onboarding/Done'));

    $this->actingAs($user)->get(route('onboarding'))->assertRedirect(route('dashboard'));
});

test('skipping setup goes to the dashboard and ends the wizard', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.skip'), ['step' => 'about'])->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->skipped_onboarding_at)->not->toBeNull()
        ->and($user->onboarding_completed_at)->not->toBeNull()
        ->and(FunnelEvent::where('event', 'onboarding_skipped')->first()->properties)->toBe(['step' => 'about']);
});

test('registering needs only an email address', function () {
    $this->post(route('register.store'), ['email' => 'lisa@acme.test'])
        ->assertSessionHas('registered', true);

    expect(User::where('email', 'lisa@acme.test')->first()->name)->toBe('lisa');

    $this->get(route('register'))
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('sentTo', 'lisa@acme.test'));
});

test('a sign-in link shows the inbox state', function () {
    User::factory()->active()->create(['email' => 'tom@acme.test']);

    $this->from(route('login'))->post(route('login.store'), ['email' => 'tom@acme.test'])
        ->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('sentTo', 'tom@acme.test'));
});
