<?php

use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The admin workspace screens render for the shapes that actually occur in production,
 * including the awkward ones: a workspace nobody is left in, and one with no usage.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->admin = User::factory()->active()->create(['is_admin' => true]);
});

test('the list renders with a mix of workspaces, including one with no members', function () {
    $owner = User::factory()->active()->create();
    Display::factory()->count(2)->create(['workspace_id' => $owner->primaryWorkspace()->id]);
    Board::factory()->create(['workspace_id' => $owner->primaryWorkspace()->id]);

    $abandoned = Workspace::factory()->create(['name' => 'Nobody Left']);

    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.index'))
        ->assertOk()
        ->assertSee($owner->primaryWorkspace()->name)
        ->assertSee('Nobody Left')
        // 2 displays + 1 board x 2 = 4 units, read from the counters.
        ->assertSee('>4<', false);

    expect($abandoned->billingOwner())->toBeNull();
});

test('the detail page renders for a workspace with no members and no usage', function () {
    $abandoned = Workspace::factory()->create(['name' => 'Nobody Left']);

    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.show', $abandoned))
        ->assertOk()
        ->assertSee('Nobody Left')
        ->assertSee('Nobody')
        ->assertSee('Total units');
});

test('a non-admin cannot reach the workspace screens', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->get(route('admin.workspaces.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.workspaces.show', $user->primaryWorkspace()))->assertForbidden();
    $this->actingAs($user)
        ->post(route('admin.workspaces.billing', $user->primaryWorkspace()), ['is_manually_billed' => '1'])
        ->assertForbidden();
});
