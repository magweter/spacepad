<?php

use App\Enums\DisplayStatus;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FreePlanDisplayLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

/**
 * The free plan has one active display per workspace. Displays beyond that one wait for
 * Pro, and come back on their own once the workspace has it.
 */

/**
 * @return array{0: Workspace, 1: User}
 */
function freeWorkspace(): array
{
    $owner = User::factory()->active()->create();

    return [$owner->primaryWorkspace(), $owner];
}

function displayIn(Workspace $workspace, User $user, DisplayStatus $status, string $createdAt): Display
{
    $display = Display::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => $status,
    ]);
    $display->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $display->fresh();
}

function freePlanLimit(): FreePlanDisplayLimit
{
    return app(FreePlanDisplayLimit::class);
}

test('the oldest live display stays, the others need Pro', function () {
    [$workspace, $owner] = freeWorkspace();
    $oldest = displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-01-01');
    $newer = displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-02-01');
    $ready = displayIn($workspace, $owner, DisplayStatus::READY, '2026-03-01');

    expect(freePlanLimit()->apply($workspace))->toBe(2);

    expect($oldest->fresh()->status)->toBe(DisplayStatus::ACTIVE)
        ->and($newer->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO)
        ->and($ready->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});

test('a display someone switched off does not count as the active one', function () {
    [$workspace, $owner] = freeWorkspace();
    $off = displayIn($workspace, $owner, DisplayStatus::DEACTIVATED, '2026-01-01');
    $live = displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-02-01');
    $extra = displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-03-01');

    freePlanLimit()->apply($workspace);

    expect($off->fresh()->status)->toBe(DisplayStatus::DEACTIVATED)
        ->and($live->fresh()->status)->toBe(DisplayStatus::ACTIVE)
        ->and($extra->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});

test('a Pro workspace keeps every display', function () {
    [$workspace, $owner] = freeWorkspace();
    $workspace->update(['is_unlimited' => true]);
    displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-01-01');
    $second = displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-02-01');

    expect(freePlanLimit()->apply($workspace->fresh()))->toBe(0)
        ->and($second->fresh()->status)->toBe(DisplayStatus::ACTIVE);
});

test('a self-hosted instance keeps every display', function () {
    config(['settings.is_self_hosted' => true]);
    [$workspace, $owner] = freeWorkspace();
    displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-01-01');
    $second = displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-02-01');

    expect(freePlanLimit()->apply($workspace))->toBe(0)
        ->and($second->fresh()->status)->toBe(DisplayStatus::ACTIVE);
});

test('upgrading brings every display back', function () {
    [$workspace, $owner] = freeWorkspace();
    $paired = displayIn($workspace, $owner, DisplayStatus::REQUIRES_PRO, '2026-02-01');
    Device::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id, 'display_id' => $paired->id]);
    $unpaired = displayIn($workspace, $owner, DisplayStatus::REQUIRES_PRO, '2026-03-01');

    expect(freePlanLimit()->release($workspace))->toBe(0);

    $workspace->update(['is_unlimited' => true]);

    expect($paired->fresh()->status)->toBe(DisplayStatus::ACTIVE)
        ->and($unpaired->fresh()->status)->toBe(DisplayStatus::READY);
});

test('another display can be made the active one', function () {
    [$workspace, $owner] = freeWorkspace();
    $current = displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-01-01');
    $waiting = displayIn($workspace, $owner, DisplayStatus::REQUIRES_PRO, '2026-02-01');

    $this->actingAs($owner)
        ->post(route('displays.makeActive', $waiting))
        ->assertRedirect(route('displays.index', ['display' => $waiting->id]))
        ->assertSessionHas('success');

    expect($waiting->fresh()->status)->toBe(DisplayStatus::READY)
        ->and($current->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});

test('someone outside the workspace cannot pick the active display', function () {
    [$workspace, $owner] = freeWorkspace();
    displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-01-01');
    $waiting = displayIn($workspace, $owner, DisplayStatus::REQUIRES_PRO, '2026-02-01');

    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)
        ->post(route('displays.makeActive', $waiting))
        ->assertForbidden();

    expect($waiting->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});

test('the tablet gets a 400 for a display that needs Pro, so it stays paired', function () {
    [$workspace, $owner] = freeWorkspace();
    $display = displayIn($workspace, $owner, DisplayStatus::REQUIRES_PRO, '2026-01-01');
    $device = Device::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id, 'display_id' => $display->id]);

    $this->actingAs($device)
        ->getJson("/api/displays/{$display->id}/data")
        ->assertStatus(400);
});

test('pairing a tablet does not wake a display that needs Pro', function () {
    [$workspace, $owner] = freeWorkspace();
    $display = displayIn($workspace, $owner, DisplayStatus::REQUIRES_PRO, '2026-01-01');
    $device = Device::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);

    $this->actingAs($device)
        ->putJson('/api/devices/display', ['display_id' => $display->id]);

    expect($display->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});

test('resuming a switched-off display is refused while another one is live', function () {
    [$workspace, $owner] = freeWorkspace();
    displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-01-01');
    $off = displayIn($workspace, $owner, DisplayStatus::DEACTIVATED, '2026-02-01');

    $this->actingAs($owner)
        ->from(route('dashboard'))
        ->patch(route('displays.updateStatus', $off), ['status' => 'active'])
        ->assertSessionHas('error');

    expect($off->fresh()->status)->toBe(DisplayStatus::DEACTIVATED);
});

test('the displays page marks a display that needs Pro and offers to make it active', function () {
    [$workspace, $owner] = freeWorkspace();
    displayIn($workspace, $owner, DisplayStatus::ACTIVE, '2026-01-01');
    $waiting = displayIn($workspace, $owner, DisplayStatus::REQUIRES_PRO, '2026-02-01');

    $this->actingAs($owner)
        ->get(route('displays.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Displays/Index')
            ->where('displays', fn ($displays) => collect($displays)->contains(fn ($d) => $d['id'] === $waiting->id
                && $d['status'] === 'requires_pro'
                && $d['statusLabel'] === 'Requires Pro'
                && $d['urls']['makeActive'] === route('displays.makeActive', $waiting))));
});
