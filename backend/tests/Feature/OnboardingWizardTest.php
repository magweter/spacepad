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
 * Onboarding is four quick questions and a choice of how to start. Setting up a display is a
 * separate flow, offered at the end and from the portal.
 */
function newcomer(): User
{
    return User::factory()->create([
        'name' => 'sanne.de.vries',
        'email' => 'sanne.de.vries@acme.test',
        'usage_type' => null,
        'email_verified_at' => now(),
        'terms_accepted_at' => now(),
    ]);
}

function stepsRecorded(User $user): array
{
    return FunnelEvent::where('workspace_id', $user->primaryWorkspace()->id)
        ->whereIn('event', ['onboarding_step_completed', 'onboarding_step_skipped'])
        ->orderBy('id')
        ->get()
        ->map(fn ($e) => $e->event.':'.$e->properties['step'])
        ->all();
}

test('a newcomer lands on the first of four steps', function () {
    $user = newcomer();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding'));

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Wizard')
            ->where('step', 'workspace')
            ->where('steps', ['workspace', 'rooms', 'tablets', 'start'])
            ->where('user.firstName', 'Sanne')
            ->has('options.rooms', 4)
            ->has('options.locations', 3)
            ->has('options.tablets', 4)
            ->has('options.features', 6));
});

test('the workspace step saves the usage and the workspace name', function () {
    $user = newcomer();

    $this->actingAs($user)
        ->post(route('onboarding.workspace'), ['usage_type' => 'business', 'workspace_name' => 'Acme HQ, Amsterdam'])
        ->assertRedirect(route('onboarding', ['step' => 'rooms']));

    expect($user->fresh()->usage_type)->toBe(UsageType::BUSINESS)
        ->and($user->primaryWorkspace()->name)->toBe('Acme HQ, Amsterdam')
        ->and(stepsRecorded($user))->toBe(['onboarding_step_completed:workspace']);
});

test('a self-hosted instance asks to accept the licence terms', function () {
    config(['settings.is_self_hosted' => true]);
    $user = newcomer();
    $user->update(['terms_accepted_at' => null]);

    $this->actingAs($user)->post(route('onboarding.workspace'), ['usage_type' => 'business'])
        ->assertSessionHasErrors('accept_terms');

    $this->actingAs($user)->post(route('onboarding.workspace'), ['usage_type' => 'business', 'accept_terms' => true])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->terms_accepted_at)->not->toBeNull();
});

test('the rooms step stores what they use, how many rooms and where', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);
    $workspace = $user->primaryWorkspace();

    $this->actingAs($user)
        ->post(route('onboarding.rooms'), ['previous_solution' => 'other', 'previous_solution_text' => 'Meetio', 'rooms' => '6-25'])
        ->assertRedirect(route('onboarding', ['step' => 'tablets']));

    expect($workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)->text)->toBe('Meetio')
        ->and($workspace->latestAnswer(OnboardingQuestion::ROOMS)->value)->toBe('6-25')
        ->and($workspace->latestAnswer(OnboardingQuestion::LOCATIONS)->value)->toBe('skipped')
        ->and(OnboardingAnswer::first()->source)->toBe('onboarding');
});

test('something else needs a name', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.rooms'), ['previous_solution' => 'other'])
        ->assertSessionHasErrors('previous_solution_text');
});

test('skipping a step stores its questions as skipped', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.rooms'), ['skip' => true]);

    expect(OnboardingAnswer::pluck('value')->unique()->all())->toBe(['skipped'])
        ->and(stepsRecorded($user))->toBe(['onboarding_step_skipped:rooms']);
});

test('the tablets step stores the tablets and every picked feature', function () {
    $user = newcomer();
    $workspace = $user->primaryWorkspace();

    $this->actingAs($user)
        ->post(route('onboarding.tablets'), ['tablets' => 'ipad', 'features' => ['check_in', 'board']])
        ->assertRedirect(route('onboarding', ['step' => 'start']));

    expect($workspace->latestAnswer(OnboardingQuestion::TABLETS)->value)->toBe('ipad')
        ->and(OnboardingAnswer::where('question', 'features')->pluck('value')->all())->toEqualCanonicalizing(['check_in', 'board']);
});

test('the wizard resumes at the first question that is not answered', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);
    $this->actingAs($user)->post(route('onboarding.rooms'), ['skip' => true]);

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->where('step', 'tablets'));
});

test('choosing the first display finishes onboarding and opens the display setup', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)->post(route('onboarding.start'), ['choice' => 'first_display'])
        ->assertRedirect(route('setup.first-display'));

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and(FunnelEvent::where('event', 'onboarding_completed')->first()->properties)->toBe(['start' => 'first_display']);

    $this->actingAs($user)->get(route('onboarding'))->assertRedirect(route('dashboard'));
});

test('looking around ends on the dashboard, even without a calendar', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)->post(route('onboarding.start'), ['choice' => 'look_around'])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Set up your first display');
});

test('skipping setup goes to the dashboard and ends onboarding', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.skip'), ['step' => 'rooms'])->assertRedirect(route('dashboard'));

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and(FunnelEvent::where('event', 'onboarding_skipped')->first()->properties)->toBe(['step' => 'rooms']);
});

test('a step is recorded as viewed once', function () {
    $user = newcomer();

    $this->actingAs($user)->postJson(route('onboarding.events'), ['step' => 'rooms', 'event' => 'viewed'])->assertNoContent();
    $this->actingAs($user)->postJson(route('onboarding.events'), ['step' => 'rooms', 'event' => 'viewed'])->assertNoContent();
    $this->actingAs($user)->postJson(route('onboarding.events'), ['step' => 'nowhere', 'event' => 'viewed'])->assertUnprocessable();

    expect(FunnelEvent::where('event', 'onboarding_step_viewed')->count())->toBe(1);
});

test('the display setup starts at the calendar without an account', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS, 'onboarding_completed_at' => now()]);

    $this->actingAs($user)->get(route('setup.first-display'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('FirstDisplay/Flow')
            ->where('step', 'calendar')
            ->where('steps', ['calendar', 'room', 'tablet', 'done'])
            ->has('shell.nav'));
});

test('connecting a calendar from the setup returns to the room step', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('outlook-accounts.auth'), ['permission_type' => 'read', 'onboarding' => 1]);

    expect(session('onboarding_return'))->toBeTrue()
        ->and(OnboardingReturn::redirect('room')->getTargetUrl())->toBe(route('setup.first-display', ['step' => 'room']))
        ->and(OnboardingReturn::redirect('room')->getTargetUrl())->toBe(route('dashboard'));
});

test('creating the display from the setup continues with the tablet for that display', function () {
    $user = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);
    $account = OutlookAccount::where('user_id', $user->id)->first();

    $response = $this->actingAs($user)->post(route('displays.store'), [
        'name' => 'Boardroom',
        'displayName' => 'Boardroom',
        'provider' => 'outlook',
        'account' => $account->id,
        'room' => 'boardroom@example.com,Boardroom',
        'from_onboarding' => 1,
    ]);

    $display = Display::sole();
    $response->assertRedirect(route('setup.first-display', ['step' => 'tablet', 'display' => $display->id]));
    expect(stepsRecorded($user))->toBe(['onboarding_step_completed:room']);
});

test('the setup opens where it was left and sees a paired tablet', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $display = Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);

    $this->actingAs($user)->get(route('setup.first-display'))
        ->assertInertia(fn (Assert $page) => $page->where('step', 'tablet')->where('display.id', $display->id)->whereNot('connectCode', null));

    $this->actingAs($user)->getJson(route('setup.first-display.device-status'))->assertJson(['paired' => false]);

    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'display_id' => $display->id, 'name' => 'Lobby iPad']);

    $this->actingAs($user)->getJson(route('setup.first-display.device-status'))->assertJson(['paired' => true, 'name' => 'Lobby iPad']);
    $this->actingAs($user)->get(route('setup.first-display'))->assertInertia(fn (Assert $page) => $page->where('step', 'done'));
});

test('the live screen shows the display and the tablet', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $display = Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'display_name' => 'Boardroom']);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'display_id' => $display->id, 'name' => 'Lobby iPad']);

    $this->actingAs($user)->get(route('setup.first-display.live', ['display' => $display->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('FirstDisplay/Live')
            ->where('display.name', 'Boardroom')
            ->where('device.name', 'Lobby iPad'));
});

test('registering needs only an email address', function () {
    $this->post(route('register.store'), ['email' => 'lisa@acme.test'])->assertSessionHas('registered', true);

    expect(User::where('email', 'lisa@acme.test')->first()->name)->toBe('lisa');

    $this->get(route('register'))
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('sentTo', 'lisa@acme.test'));
});

test('a sign-in link shows the inbox state', function () {
    User::factory()->active()->create(['email' => 'tom@acme.test']);

    $this->from(route('login'))->post(route('login.store'), ['email' => 'tom@acme.test'])->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('sentTo', 'tom@acme.test'));
});
