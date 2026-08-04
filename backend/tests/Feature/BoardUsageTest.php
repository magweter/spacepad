<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Usage is billed per workspace, so it is shown on Manage workspace rather than on the
 * personal account page — which must stay the same whichever workspace is selected.
 */
test('the manage workspace page shows the usage breakdown for that workspace', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    session()->put('selected_workspace_id', $workspace->id);

    Display::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    Board::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('workspaces.members'));

    $response->assertStatus(200);
    $response->assertViewIs('pages.workspaces.members');
    $response->assertViewHas('usageBreakdown', function ($breakdown) {
        return $breakdown['displays'] === 3
            && $breakdown['boards'] === 2
            && $breakdown['board_usage'] === 4
            && $breakdown['total'] === 7;
    });
});

test('the usage breakdown is passed even without Pro', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    session()->put('selected_workspace_id', $workspace->id);

    Display::factory()->count(1)->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    Board::factory()->count(1)->create([
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('workspaces.members'));

    $response->assertStatus(200);
    $response->assertViewHas('usageBreakdown');
});

test('the account page no longer carries workspace billing', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    Display::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $response = $this->actingAs($user)->get(route('profile.show'));

    $response->assertStatus(200);
    $response->assertViewIs('pages.profile');
    $response->assertViewMissing('usageBreakdown');
    $response->assertDontSee('Total billed to subscription');
});
