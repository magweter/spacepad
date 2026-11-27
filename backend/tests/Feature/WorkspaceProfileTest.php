<?php

use App\Enums\FunnelStep;
use App\Enums\OnboardingQuestion;
use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Models\Display;
use App\Models\FunnelEvent;
use App\Models\OnboardingAnswer;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

/**
 * Two optional questions on the dashboard, after the first display: how many rooms, and
 * what the workspace used before. Answers land in onboarding_answers, one row per click.
 */

/**
 * @return array{0: Workspace, 1: User}
 */
function businessWorkspaceWithDisplay(): array
{
    $owner = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);
    $workspace = $owner->primaryWorkspace();
    Display::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);

    return [$workspace, $owner];
}

function answerProfile(User $user, array $payload)
{
    return test()->actingAs($user)->postJson(route('workspace.profile.update'), $payload);
}

test('the owner sees the card after the first display, with the options of the onboarding design', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileCard.firstDisplay', true)
            ->where('profileCard.roomOptions.2.label', '6 to 25')
            ->where('profileCard.solutionOptions.1.label', 'Outlook or Google only')
            ->where('profileCard.solutionOptions.5.label', 'Pronestor'));

    expect(FunnelEvent::where('workspace_id', $workspace->id)->where('event', 'profile_card_shown')->count())->toBe(1);

    $this->actingAs($owner)->get(route('dashboard'));
    expect(FunnelEvent::where('workspace_id', $workspace->id)->where('event', 'profile_card_shown')->count())->toBe(1);
});

test('nobody is asked before the first display', function () {
    $owner = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('profileCard', null));
});

test('a member, a personal user and a self-hosted instance are not asked', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    $member = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);
    expect($workspace->shouldAskProfile($member))->toBeFalse();

    $owner->update(['usage_type' => UsageType::PERSONAL]);
    expect($workspace->fresh()->shouldAskProfile($owner->fresh()))->toBeFalse();

    $owner->update(['usage_type' => UsageType::BUSINESS]);
    config(['settings.is_self_hosted' => true]);
    expect($workspace->fresh()->shouldAskProfile($owner->fresh()))->toBeFalse();
});

test('each click is stored as its own answer, with who gave it and where', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    answerProfile($owner, ['question' => 'rooms', 'value' => '6-25'])->assertNoContent();

    $answer = OnboardingAnswer::sole();
    expect($answer->workspace_id)->toBe($workspace->id)
        ->and($answer->user_id)->toBe($owner->id)
        ->and($answer->question)->toBe('rooms')
        ->and($answer->value)->toBe('6-25')
        ->and($answer->source)->toBe('dashboard_card')
        ->and($workspace->shouldAskProfile($owner))->toBeTrue();
});

test('a changed answer keeps the earlier one as history', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    answerProfile($owner, ['question' => 'rooms', 'value' => '1']);
    $this->travel(1)->minutes();
    answerProfile($owner, ['question' => 'rooms', 'value' => '25+']);

    expect(OnboardingAnswer::count())->toBe(2)
        ->and($workspace->latestAnswer(OnboardingQuestion::ROOMS)->value)->toBe('25+');
});

test('answering the second question ends the card', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    answerProfile($owner, ['question' => 'previous_solution', 'value' => 'calendar_only'])->assertNoContent();

    expect($workspace->shouldAskProfile($owner))->toBeFalse();
});

test('something else needs a name, which is stored with the answer', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    answerProfile($owner, ['question' => 'previous_solution', 'value' => 'other'])->assertUnprocessable();
    answerProfile($owner, ['question' => 'previous_solution', 'value' => 'other', 'text' => 'Meetio'])->assertNoContent();

    expect($workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)->text)->toBe('Meetio');
});

test('text is only kept for the option that asks for it', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    answerProfile($owner, ['question' => 'previous_solution', 'value' => 'joan', 'text' => 'stray'])->assertNoContent();

    expect($workspace->latestAnswer(OnboardingQuestion::PREVIOUS_SOLUTION)->text)->toBeNull();
});

test('an unknown question or option is refused', function () {
    [, $owner] = businessWorkspaceWithDisplay();

    answerProfile($owner, ['question' => 'shoe_size', 'value' => '42'])->assertUnprocessable();
    answerProfile($owner, ['question' => 'rooms', 'value' => '500'])->assertUnprocessable();

    expect(OnboardingAnswer::count())->toBe(0);
});

test('a question can be skipped explicitly', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    answerProfile($owner, ['question' => 'rooms', 'value' => 'skipped'])->assertNoContent();

    expect($workspace->latestAnswer(OnboardingQuestion::ROOMS)->value)->toBe(OnboardingAnswer::SKIPPED);
});

test('closing the card keeps earlier answers and hides it for good', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();
    answerProfile($owner, ['question' => 'rooms', 'value' => '2-5']);

    $this->actingAs($owner)->postJson(route('workspace.profile.dismiss'))->assertNoContent();

    expect($workspace->latestAnswer(OnboardingQuestion::ROOMS)->value)->toBe('2-5')
        ->and(FunnelEvent::where('event', FunnelStep::PROFILE_CARD_DISMISSED->value)->count())->toBe(1)
        ->and($workspace->shouldAskProfile($owner))->toBeFalse();

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('profileCard', null));
});

test('a member cannot answer for the workspace', function () {
    [$workspace] = businessWorkspaceWithDisplay();
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    $this->actingAs($member)
        ->withSession(['selected_workspace_id' => $workspace->id])
        ->postJson(route('workspace.profile.update'), ['question' => 'rooms', 'value' => '1'])
        ->assertForbidden();

    expect(OnboardingAnswer::count())->toBe(0);
});

test('the latest answers reach the analytics snapshot, with the room range as numbers', function () {
    foreach ([
        '2026_05_30_000001_create_analytics_tables.php',
        '2026_05_30_000003_add_subscription_columns_to_analytics_instances.php',
        '2026_07_05_000001_create_billing_changes_table.php',
        '2026_08_04_000002_add_workspace_to_billing_changes.php',
        '2026_08_05_000002_make_billing_change_mrr_nullable.php',
        '2026_08_05_000003_create_analytics_workspaces_table.php',
        '2026_11_01_000003_add_profile_and_cancellation_to_analytics_workspaces.php',
    ] as $file) {
        (require database_path("migrations/{$file}"))->up();
    }

    [$workspace, $owner] = businessWorkspaceWithDisplay();
    answerProfile($owner, ['question' => 'rooms', 'value' => '25+']);
    answerProfile($owner, ['question' => 'previous_solution', 'value' => 'other', 'text' => 'Meetio']);

    $this->artisan('app:refresh-analytics')->assertSuccessful();

    $row = DB::table('analytics_workspaces')->where('workspace_id', $workspace->id)->first();
    expect($row->rooms_estimate)->toBe('25+')
        ->and((int) $row->rooms_min)->toBe(26)
        ->and($row->rooms_max)->toBeNull()
        ->and($row->previous_solution)->toBe('other')
        ->and($row->previous_solution_other)->toBe('Meetio');
});
