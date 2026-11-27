<?php

use App\Enums\Feature;
use App\Enums\OnboardingQuestion;
use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Models\Device;
use App\Models\Display;
use App\Models\FunnelEvent;
use App\Models\OnboardingAnswer;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\WorkspaceJoinRequest;
use App\Models\WorkspaceMember;
use App\Notifications\ItAdminCalendarRequest;
use App\Notifications\WorkspaceJoinRequested;
use App\Services\OnboardingAnswers;
use App\Support\OnboardingReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            ->where('user.email', 'sanne.de.vries@acme.test')
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

test('the organisation step stores how many rooms, where and every picked use', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);
    $workspace = $user->primaryWorkspace();

    $this->actingAs($user)
        ->post(route('onboarding.rooms'), ['rooms' => '6-25', 'features' => ['check_in', 'board']])
        ->assertRedirect(route('onboarding', ['step' => 'tablets']));

    expect($workspace->latestAnswer(OnboardingQuestion::ROOMS)->value)->toBe('6-25')
        ->and($workspace->latestAnswer(OnboardingQuestion::LOCATIONS)->value)->toBe('skipped')
        ->and(OnboardingAnswer::where('question', 'features')->pluck('value')->all())->toEqualCanonicalizing(['check_in', 'board'])
        ->and(OnboardingAnswer::first()->source)->toBe('onboarding');
});

test('something else needs a name', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.tablets'), ['previous_solution' => 'other'])
        ->assertSessionHasErrors('previous_solution_text');
});

test('skipping a step stores its questions as skipped', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.rooms'), ['skip' => true]);

    expect(OnboardingAnswer::pluck('value')->unique()->all())->toBe(['skipped'])
        ->and(stepsRecorded($user))->toBe(['onboarding_step_skipped:rooms']);
});

test('the current setup step stores the tablets and what they use now', function () {
    $user = newcomer();
    $workspace = $user->primaryWorkspace();

    $this->actingAs($user)
        ->post(route('onboarding.tablets'), ['tablets' => 'ipad', 'previous_solution' => 'other', 'previous_solution_text' => 'Meetio'])
        ->assertRedirect(route('onboarding', ['step' => 'start']));

    expect($workspace->latestAnswer(OnboardingQuestion::TABLETS)->value)->toBe('ipad')
        ->and($workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)->value)->toBe('other')
        ->and($workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)->text)->toBe('Meetio');
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

    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Overview/Index')->where('checklist.display', false)->where('urls.newDisplay', route('setup.first-display')));
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
            ->where('steps', ['calendar', 'room', 'tablet'])
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
    // Paired: the pairing step itself shows it, with the next steps; there is no "done" screen.
    $this->actingAs($user)->get(route('setup.first-display'))
        ->assertInertia(fn (Assert $page) => $page->where('step', 'tablet')->where('device.name', 'Lobby iPad'));
    $this->actingAs($user)->get(route('setup.first-display', ['step' => 'done']))
        ->assertInertia(fn (Assert $page) => $page->where('step', 'tablet'));
});

test('the live screen shows the display and the tablet', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $display = Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'display_name' => 'Boardroom']);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'display_id' => $display->id, 'name' => 'Lobby iPad']);

    $this->actingAs($user)->get(route('setup.first-display.live', ['display' => $display->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('FirstDisplay/Live')
            ->has('shell')
            ->where('display.name', 'Boardroom')
            ->where('device.name', 'Lobby iPad')
            ->where('urls', ['dashboard' => route('dashboard')]));
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

test('the first step cannot be skipped', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.workspace'), ['skip' => true])
        ->assertSessionHasErrors('usage_type');

    expect(stepsRecorded($user))->not->toContain('onboarding_step_skipped:workspace');
});

test('the licence terms on a self-hosted instance cannot be skipped', function () {
    config(['settings.is_self_hosted' => true]);
    $user = newcomer();
    $user->update(['terms_accepted_at' => null]);

    $this->actingAs($user)->post(route('onboarding.workspace'), ['skip' => true, 'usage_type' => 'business'])
        ->assertSessionHasErrors('accept_terms');
});

test('skipping the whole wizard on a self-hosted instance still needs the licence terms', function () {
    config(['settings.is_self_hosted' => true]);
    $user = newcomer();
    $user->update(['terms_accepted_at' => null, 'usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)->post(route('onboarding.skip'), ['step' => 'rooms'])->assertRedirect(route('onboarding'));

    expect($user->fresh()->onboarding_completed_at)->toBeNull();
});

test('a finished wizard does not count on a self-hosted instance until the licence is accepted', function () {
    config(['settings.is_self_hosted' => true]);
    $user = newcomer();
    $user->update(['terms_accepted_at' => null, 'usage_type' => UsageType::BUSINESS, 'onboarding_completed_at' => now()]);

    expect($user->fresh()->isOnboarded())->toBeFalse();

    $user->update(['terms_accepted_at' => now()]);
    expect($user->fresh()->isOnboarded())->toBeTrue();
});

test('the backfill only marks people the old onboarding let through', function () {
    $halfway = User::factory()->create(['usage_type' => UsageType::BUSINESS, 'skipped_onboarding_at' => null]);
    $skipped = User::factory()->create(['usage_type' => UsageType::BUSINESS, 'skipped_onboarding_at' => now()->subDay()]);
    $connected = User::factory()->create(['usage_type' => UsageType::BUSINESS, 'skipped_onboarding_at' => null]);
    OutlookAccount::factory()->create(['user_id' => $connected->id, 'workspace_id' => $connected->primaryWorkspace()?->id]);
    $colleague = User::factory()->create(['usage_type' => UsageType::BUSINESS, 'skipped_onboarding_at' => null]);
    WorkspaceMember::create(['workspace_id' => $connected->primaryWorkspace()->id, 'user_id' => $colleague->id, 'role' => WorkspaceRole::MEMBER]);
    $nothing = User::factory()->create(['usage_type' => null, 'skipped_onboarding_at' => now()->subDay()]);

    User::query()->update(['onboarding_completed_at' => null]);
    $migration = require database_path('migrations/2026_11_08_000001_add_onboarding_completed_at_to_users_table.php');
    $migration->down();
    $migration->up();

    expect($halfway->fresh()->onboarding_completed_at)->toBeNull()
        ->and($skipped->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and($connected->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and($colleague->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and($nothing->fresh()->onboarding_completed_at)->toBeNull();
});

test('someone without admin rights can mail a request to IT', function () {
    Notification::fake();
    $user = User::factory()->active()->create(['name' => 'Sanne de Vries']);

    $this->actingAs($user)->from(route('setup.first-display'))
        ->post(route('setup.first-display.it-request'), ['email' => 'it@acme.test'])
        ->assertRedirect(route('setup.first-display'))
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(
        ItAdminCalendarRequest::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'it@acme.test'
            && $notification->requesterName === 'Sanne de Vries'
    );
    expect(FunnelEvent::where('event', 'it_request_sent')->count())->toBe(1);

    $this->actingAs($user)->post(route('setup.first-display.it-request'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('the workspace name is suggested from the company domain', function () {
    $user = User::factory()->create(['name' => 'jan jansen', 'email' => 'jan@cbyte.nl', 'usage_type' => null, 'terms_accepted_at' => now()]);
    $user->primaryWorkspace()->update(['name' => "jan jansen's Workspace"]);

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->component('Onboarding/Wizard')->where('workspace.name', 'Cbyte'));
});

test('a personal address gets its own name in title case', function () {
    $user = User::factory()->create(['name' => 'jan jansen', 'email' => 'jan@gmail.com', 'usage_type' => null, 'terms_accepted_at' => now()]);
    $user->primaryWorkspace()->update(['name' => "jan jansen's Workspace"]);

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->where('workspace.name', "Jan Jansen's Workspace"));
});

test('without tablets the hardware guide question is stored', function () {
    $user = newcomer();

    $this->actingAs($user)->post(route('onboarding.tablets'), ['tablets' => 'none', 'hardware_guide' => true]);

    expect($user->primaryWorkspace()->latestAnswer(OnboardingQuestion::HARDWARE_GUIDE)->value)->toBe('yes');
});

test('looking around leaves the inertia pages with a full page visit', function () {
    $user = newcomer();
    $user->update(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($user)->post(route('onboarding.start'), ['choice' => 'look_around'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'));
});

test('a colleague is offered the workspace of their company', function () {
    Notification::fake();
    $owner = User::factory()->active()->create(['email' => 'owner@acme.test']);
    $colleague = newcomer();

    $this->actingAs($colleague)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->component('Onboarding/JoinExisting')
            ->where('sent', false)
            ->where('workspace.name', $owner->primaryWorkspace()->name)
            ->where('user.email', 'sanne.de.vries@acme.test'));

    $this->actingAs($colleague)->post(route('onboarding.join'))->assertRedirect(route('onboarding'));

    Notification::assertSentTo($owner, WorkspaceJoinRequested::class);
    $this->actingAs($colleague)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->component('Onboarding/JoinExisting')->where('sent', true));
});

test('a colleague can set up a separate workspace instead', function () {
    User::factory()->active()->create(['email' => 'owner@acme.test']);
    $colleague = newcomer();

    $this->actingAs($colleague)->post(route('onboarding.separate'))->assertRedirect(route('onboarding', ['step' => 'workspace']));

    $this->actingAs($colleague)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->component('Onboarding/Wizard'));
});

test('a free email address is never matched to a workspace', function () {
    User::factory()->active()->create(['email' => 'someone@gmail.com']);
    $user = User::factory()->create(['email' => 'other@gmail.com', 'usage_type' => null, 'terms_accepted_at' => now()]);

    $this->actingAs($user)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->component('Onboarding/Wizard'));
});

test('declining a join request sends them back to their own setup', function () {
    $owner = User::factory()->active()->create(['email' => 'owner@acme.test']);
    $colleague = newcomer();
    $joinRequest = WorkspaceJoinRequest::create(['workspace_id' => $owner->primaryWorkspace()->id, 'user_id' => $colleague->id]);

    $this->actingAs($owner)->post(route('join-requests.decline', $joinRequest))->assertRedirect(route('join-requests.show', $joinRequest));

    expect($joinRequest->fresh()->status)->toBe(WorkspaceJoinRequest::DECLINED);
    $this->actingAs($colleague)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->component('Onboarding/Wizard'));
});

test('only an owner or admin decides on a join request', function () {
    $owner = User::factory()->active()->create(['email' => 'owner@acme.test']);
    $stranger = User::factory()->active()->create();
    $joinRequest = WorkspaceJoinRequest::create(['workspace_id' => $owner->primaryWorkspace()->id, 'user_id' => newcomer()->id]);

    $this->actingAs($stranger)->get(route('join-requests.show', $joinRequest))->assertForbidden();
    $this->actingAs($stranger)->post(route('join-requests.decline', $joinRequest))->assertForbidden();
});

test('the organisation step offers six needs at most, without room displays', function () {
    $values = collect(OnboardingAnswers::options(OnboardingQuestion::FEATURES))->pluck('value');

    expect($values->all())->toBe(['booking', 'check_in', 'board', 'live_view', 'find_a_room', 'branding'])
        // An earlier answer of room displays still reads as one.
        ->and(Feature::from('room_displays')->label())->toBe('Room displays');
});

test('a tablet of another display does not count as paired to this one', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $other = Display::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $display = Display::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'created_at' => now()->subDay()]);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'display_id' => $other->id, 'name' => 'Other room iPad']);

    $this->actingAs($user)
        ->getJson(route('setup.first-display.device-status', ['display' => $display->id]))
        ->assertJson(['paired' => false]);

    // A tablet paired since, that has not picked a room yet, does count.
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'display_id' => null, 'name' => 'New iPad']);
    $this->actingAs($user)
        ->getJson(route('setup.first-display.device-status', ['display' => $display->id]))
        ->assertJson(['paired' => true, 'name' => 'New iPad']);
});
