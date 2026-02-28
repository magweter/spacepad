<?php

namespace Tests\Unit;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('workspace calculates total usage correctly with only displays', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();
    
    Display::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
    
    $usage = $workspace->getTotalUsageCount();
    expect($usage)->toBe(3);
});

test('workspace calculates total usage correctly with only boards', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();
    
    Board::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
    ]);
    
    $usage = $workspace->getTotalUsageCount();
    expect($usage)->toBe(4); // 2 boards * 2 = 4
});

test('workspace calculates total usage correctly with displays and boards', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();
    
    Display::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
    
    Board::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
    ]);
    
    $usage = $workspace->getTotalUsageCount();
    expect($usage)->toBe(8); // 2 displays + (3 boards * 2) = 8
});

test('workspace usage breakdown includes all components', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();
    
    Display::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
    
    Board::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
    ]);
    
    $breakdown = $workspace->getUsageBreakdown();
    
    expect($breakdown['displays'])->toBe(2);
    expect($breakdown['boards'])->toBe(3);
    expect($breakdown['board_usage'])->toBe(6); // 3 boards * 2
    expect($breakdown['total'])->toBe(8); // 2 + 6
});

test('workspace usage counts all displays regardless of status', function () {
    $user = User::factory()->create();
    $workspace = $user->primaryWorkspace();
    
    Display::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
    
    Display::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::READY,
    ]);
    
    Display::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::DEACTIVATED,
    ]);
    
    Board::factory()->count(1)->create([
        'workspace_id' => $workspace->id,
    ]);
    
    $usage = $workspace->getTotalUsageCount();
    expect($usage)->toBe(5); // 3 displays + (1 board * 2) = 5
});
