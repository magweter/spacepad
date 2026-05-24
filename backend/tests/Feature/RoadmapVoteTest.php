<?php

use App\Models\RoadmapItem;
use App\Models\RoadmapVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);

    $this->item = RoadmapItem::create([
        'title' => 'Dark mode',
        'description' => null,
        'status' => 'considering',
        'is_approved' => true,
        'sort_order' => 0,
    ]);
});

test('authenticated user can toggle a vote on an approved roadmap item', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('roadmap.vote', ['roadmapItem' => $this->item]))
        ->assertOk()
        ->assertJson(['voted' => true, 'votes_count' => 1]);

    expect(RoadmapVote::where('roadmap_item_id', $this->item->id)->where('user_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->postJson(route('roadmap.vote', ['roadmapItem' => $this->item]))
        ->assertOk()
        ->assertJson(['voted' => false, 'votes_count' => 0]);

    expect(RoadmapVote::where('roadmap_item_id', $this->item->id)->where('user_id', $user->id)->exists())->toBeFalse();
});

test('roadmap vote is not available when self hosted', function () {
    config(['settings.is_self_hosted' => true]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('roadmap.vote', ['roadmapItem' => $this->item]))
        ->assertNotFound();
});

test('support ask is not available when self hosted', function () {
    config(['settings.is_self_hosted' => true]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('support.ask'), [
            'message' => 'This is a long enough question for validation.',
        ])
        ->assertNotFound();
});
