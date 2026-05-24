<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('account page shows correct usage breakdown for workspace', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    Display::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    Board::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('profile.show'));

    $response->assertStatus(200);
    $response->assertViewIs('pages.profile');
    $response->assertViewHas('usageBreakdown', function ($breakdown) {
        return $breakdown['displays'] === 3
            && $breakdown['boards'] === 2
            && $breakdown['board_usage'] === 4
            && $breakdown['total'] === 7;
    });
});

test('account page passes usage breakdown to view', function () {
    $user = User::factory()->active()->create([
        'is_unlimited' => true,
    ]);
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
        ->get(route('profile.show'));

    $response->assertStatus(200);
    $response->assertViewHas('usageBreakdown');
});
