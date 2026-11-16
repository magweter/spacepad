<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable self-hosted mode for admin tests
    config(['settings.is_self_hosted' => false]);

    $this->admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Set selected workspace for admin
    $workspace = $this->admin->primaryWorkspace();
    session()->put('selected_workspace_id', $workspace->id);
});

test('admin can access admin index', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));

    $response->assertStatus(200);
});

test('admin can see boards count per user in users table', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();

    Board::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users')
        ->where('users', fn ($users) => collect($users)->firstWhere('email', $user->email)['boards'] === 3));
});

test('admin can see boards column in users table', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();

    Board::factory()->count(4)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users')
        ->where('users', fn ($users) => collect($users)->firstWhere('email', $user->email)['boards'] === 4));
});

test('admin page loads with instances present', function () {
    Instance::factory()->create([
        'is_self_hosted' => true,
        'displays_count' => 5,
        'rooms_count' => 2,
        'boards_count' => 3,
        'last_heartbeat_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users')
        ->has('stats.total_instances'));
});
