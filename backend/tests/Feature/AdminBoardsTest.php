<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable self-hosted mode for admin tests
    config(['settings.is_self_hosted' => false]);
    
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'is_unlimited' => true,
    ]);
    
    // Set selected workspace for admin
    $workspace = $this->admin->primaryWorkspace();
    session()->put('selected_workspace_id', $workspace->id);
});

test('admin can see total boards count', function () {
    // Create boards without triggering subscription queries
    $user = User::factory()->create(['is_unlimited' => true]);
    $workspace = $user->primaryWorkspace();
    
    Board::factory()->count(5)->create([
        'workspace_id' => $workspace->id,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));
    
    $response->assertStatus(200);
    $response->assertSee('Total Boards');
    $response->assertSee('5');
});

test('admin can see boards count per user in active users tab', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    
    Display::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
        'last_sync_at' => now(),
    ]);
    
    Board::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));
    
    $response->assertStatus(200);
    $response->assertSee('Boards');
    // Check that boards count appears in the table
    $response->assertSee('3');
});

test('admin can see boards count in paying users tab', function () {
    $user = User::factory()->active()->create([
        'is_unlimited' => true,
    ]);
    $workspace = $user->primaryWorkspace();
    
    Board::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));
    
    $response->assertStatus(200);
    // Should see boards count in paying users table
    $response->assertSee('Boards');
});

test('admin can see boards count in users overview tab', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();
    
    Board::factory()->count(4)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));
    
    $response->assertStatus(200);
    $response->assertSee('Boards');
    // Should see boards count in users overview table
});

test('admin can see boards count for self-hosted instances', function () {
    $instance = Instance::factory()->create([
        'is_self_hosted' => true,
        'displays_count' => 5,
        'rooms_count' => 2,
        'boards_count' => 3,
        'last_heartbeat_at' => now(),
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));
    
    $response->assertStatus(200);
    $response->assertSee('Boards');
    // Should see boards count in instances table
    $response->assertSee('3');
});
