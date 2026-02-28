<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Enums\DisplayStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('usage page shows correct breakdown for workspace', function () {
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
        ->get(route('usage.index'));
    
    $response->assertStatus(200);
    $response->assertViewIs('pages.usage.index');
    $response->assertViewHas('usageBreakdown', function ($breakdown) {
        return $breakdown['displays'] === 3
            && $breakdown['boards'] === 2
            && $breakdown['board_usage'] === 4
            && $breakdown['total'] === 7;
    });
});

test('usage page shows correct data structure', function () {
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
        ->get(route('usage.index'));
    
    $response->assertStatus(200);
    $response->assertViewHas('usageBreakdown');
    $response->assertViewHas('workspace');
});
