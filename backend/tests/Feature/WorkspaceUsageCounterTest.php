<?php

use App\Enums\WorkspaceRole;
use App\Events\WorkspaceUsageChanged;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceService;
use App\Services\WorkspaceTransferService;
use App\Services\WorkspaceUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function usageWorkspace(string $name = 'Counters'): Workspace
{
    $user = User::factory()->active()->create();
    $workspace = Workspace::factory()->create(['name' => $name]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    return $workspace;
}

function storedCounts(Workspace $workspace): array
{
    $row = DB::table('workspaces')->where('id', $workspace->id)->first(['displays_count', 'boards_count']);

    return [(int) $row->displays_count, (int) $row->boards_count];
}

it('starts a new workspace at zero', function () {
    expect(storedCounts(usageWorkspace()))->toBe([0, 0]);
});

it('counts a display up when it is created and down when it is deleted', function () {
    $workspace = usageWorkspace();

    $display = Display::factory()->create(['workspace_id' => $workspace->id]);
    expect(storedCounts($workspace))->toBe([1, 0]);

    $display->delete();
    expect(storedCounts($workspace))->toBe([0, 0]);
});

it('counts a board up when it is created and down when it is deleted', function () {
    $workspace = usageWorkspace();

    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    expect(storedCounts($workspace))->toBe([0, 1]);

    $board->delete();
    expect(storedCounts($workspace))->toBe([0, 0]);
});

it('resolves units as displays plus twice the boards, without querying', function () {
    $workspace = usageWorkspace();

    Display::factory()->count(3)->create(['workspace_id' => $workspace->id]);
    Board::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    $workspace->refresh();

    DB::enableQueryLog();
    $breakdown = $workspace->getUsageBreakdown();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($breakdown)->toBe(['displays' => 3, 'boards' => 2, 'board_usage' => 4, 'total' => 7]);
    expect($workspace->getTotalUsageCount())->toBe(7);
    expect($queries)->toBeEmpty();
});

it('moves a display between workspaces on both counters', function () {
    $from = usageWorkspace('From');
    $to = usageWorkspace('To');

    $display = Display::factory()->create(['workspace_id' => $from->id]);
    $display->update(['workspace_id' => $to->id]);

    expect(storedCounts($from))->toBe([0, 0]);
    expect(storedCounts($to))->toBe([1, 0]);
});

it('recounts both workspaces after a bulk transfer', function () {
    $from = usageWorkspace('From');
    $to = usageWorkspace('To');

    Display::factory()->count(2)->create(['workspace_id' => $from->id]);
    Board::factory()->create(['workspace_id' => $from->id]);

    app(WorkspaceTransferService::class)->move($from->refresh(), $to->refresh());

    expect(storedCounts($from))->toBe([0, 0]);
    expect(storedCounts($to))->toBe([2, 1]);
});

it('repairs drift when reconciling with --fix', function () {
    $workspace = usageWorkspace();
    Display::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    // Whatever the observers cannot see: a hand-written UPDATE in production.
    DB::table('workspaces')->where('id', $workspace->id)->update(['displays_count' => 99]);

    $this->artisan('app:reconcile-workspace-usage')
        ->expectsOutputToContain('displays 99 -> 2')
        ->assertSuccessful();

    expect(storedCounts($workspace))->toBe([99, 0]);

    $this->artisan('app:reconcile-workspace-usage', ['--fix' => true])->assertSuccessful();

    expect(storedCounts($workspace))->toBe([2, 0]);
});

it('announces a usage change once per billable move', function () {
    Event::fake([WorkspaceUsageChanged::class]);

    $workspace = usageWorkspace();
    Display::factory()->create(['workspace_id' => $workspace->id]);

    Event::assertDispatched(WorkspaceUsageChanged::class, function (WorkspaceUsageChanged $event) use ($workspace) {
        return $event->workspace->id === $workspace->id
            && $event->previousUnits() === 0
            && $event->newUnits() === 1
            && $event->unitDelta() === 1;
    });

    Event::assertDispatchedTimes(WorkspaceUsageChanged::class, 1);
});

it('says nothing when a recount leaves the billable total untouched', function () {
    $workspace = usageWorkspace();
    Display::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    // Two displays out, one board in: the columns move, the invoice does not.
    Display::where('workspace_id', $workspace->id)->delete();
    Board::factory()->create(['workspace_id' => $workspace->id]);
    DB::table('workspaces')->where('id', $workspace->id)->update([
        'displays_count' => 2,
        'boards_count' => 0,
    ]);

    Event::fake([WorkspaceUsageChanged::class]);

    app(WorkspaceUsageService::class)->recount($workspace->refresh());

    expect(storedCounts($workspace))->toBe([0, 1]);
    Event::assertNotDispatched(WorkspaceUsageChanged::class);
});

it('does not walk the counters down when a workspace is purged', function () {
    Event::fake([WorkspaceUsageChanged::class]);

    $workspace = usageWorkspace();
    Display::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    Event::fake([WorkspaceUsageChanged::class]);

    app(WorkspaceService::class)->purge($workspace->refresh());

    Event::assertNotDispatched(WorkspaceUsageChanged::class);
    expect(Workspace::find($workspace->id))->toBeNull();
});
