<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->create([
        'is_unlimited' => true, // Make user pro for testing boards
    ]);
    $this->workspace = $this->user->primaryWorkspace();
    
    // Set selected workspace in session
    session()->put('selected_workspace_id', $this->workspace->id);
});

test('user can create a board', function () {
    Display::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
    
    $response = $this->actingAs($this->user)
        ->post(route('boards.store'), [
            'name' => 'Test Board',
            'workspace_id' => $this->workspace->id,
            'show_all_displays' => true,
            'theme' => 'dark',
            'show_title' => true,
            'show_booker' => true,
            'show_next_event' => true,
            'show_transitioning' => true,
            'transitioning_minutes' => 10,
            'font_family' => 'Inter',
            'language' => 'en',
            'view_mode' => 'card',
            'show_meeting_title' => true,
        ]);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('boards', [
        'name' => 'Test Board',
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('user can view boards list', function () {
    Board::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    
    $response = $this->actingAs($this->user)
        ->get(route('dashboard') . '?tab=boards');
    
    $response->assertStatus(200);
    $response->assertSee('Boards');
});

test('user can view a board', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    
    $response = $this->actingAs($this->user)
        ->get(route('boards.show', $board));
    
    $response->assertStatus(200);
    $response->assertViewIs('pages.boards.show');
});

test('user can update a board', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    
    $response = $this->actingAs($this->user)
        ->put(route('boards.update', $board), [
            'name' => 'Updated Board Name',
            'workspace_id' => $this->workspace->id,
            'show_all_displays' => false,
            'theme' => 'light',
            'show_title' => true,
            'show_booker' => true,
            'show_next_event' => true,
            'show_transitioning' => true,
            'transitioning_minutes' => 10,
            'font_family' => 'Inter',
            'language' => 'en',
            'view_mode' => 'card',
            'show_meeting_title' => true,
        ]);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
        'name' => 'Updated Board Name',
        'theme' => 'light',
    ]);
});

test('user can delete a board', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    
    $response = $this->actingAs($this->user)
        ->delete(route('boards.destroy', $board));
    
    $response->assertRedirect();
    $this->assertDatabaseMissing('boards', [
        'id' => $board->id,
    ]);
});
