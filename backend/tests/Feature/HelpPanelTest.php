<?php

use App\Models\RoadmapItem;
use App\Models\RoadmapVote;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\HelpArticles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    Mail::fake();
});

test('help data requires authentication', function () {
    $this->getJson(route('help.data'))->assertUnauthorized();
});

test('help data is not available when self hosted', function () {
    config(['settings.is_self_hosted' => true]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('help.data'))
        ->assertNotFound();
});

test('help data returns the faq, categories and the user email', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('help.data'))->assertOk();

    expect($response->json('faqs'))->toHaveCount(count(HelpArticles::faqs()))
        ->and($response->json('faqs.0'))->toHaveKeys(['id', 'category', 'categoryLabel', 'question', 'answer'])
        ->and($response->json('faqs.0'))->not->toHaveKey('accentColor')
        ->and($response->json('categories'))->toHaveCount(count(HelpArticles::categories()))
        ->and($response->json('email'))->toBe($user->email);
});

test('help data lists approved roadmap items in order with counts and own votes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $considering = RoadmapItem::create(['title' => 'Dark mode', 'status' => 'considering', 'is_approved' => true, 'sort_order' => 0]);
    $building = RoadmapItem::create(['title' => 'Room booking', 'status' => 'building', 'is_approved' => true, 'sort_order' => 0, 'expected_at' => '2026-12-01']);
    RoadmapItem::create(['title' => 'Unreviewed idea', 'status' => 'considering', 'is_approved' => false]);

    RoadmapVote::create(['roadmap_item_id' => $considering->id, 'user_id' => $user->id]);
    RoadmapVote::create(['roadmap_item_id' => $considering->id, 'user_id' => $other->id]);
    RoadmapVote::create(['roadmap_item_id' => $building->id, 'user_id' => $other->id]);

    $roadmap = $this->actingAs($user)->getJson(route('help.data'))->assertOk()->json('roadmap');

    expect($roadmap)->toHaveCount(2)
        ->and(array_column($roadmap, 'title'))->toBe(['Room booking', 'Dark mode'])
        ->and($roadmap[0])->toMatchArray([
            'id' => $building->id,
            'status' => 'building',
            'statusLabel' => 'Building now',
            'expectedAt' => 'Dec 2026',
            'votesCount' => 1,
            'voted' => false,
        ])
        ->and($roadmap[1])->toMatchArray(['votesCount' => 2, 'voted' => true]);
});

test('asking a question as json stores it and returns json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('support.ask'), ['message' => 'How do I connect a second tablet?'])
        ->assertOk()
        ->assertJson(['sent' => true, 'email' => $user->email]);

    expect(SupportMessage::where('user_id', $user->id)->count())->toBe(1);
});

test('asking a question as json returns validation errors', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('support.ask'), ['message' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    expect(SupportMessage::count())->toBe(0);
});

test('asking a question from the blade form still redirects back with a flash', function () {
    $this->actingAs(User::factory()->create())
        ->from('/dashboard')
        ->post(route('support.ask'), ['message' => 'How do I connect a second tablet?'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('support_sent', true);
});

test('suggesting a feature as json stores an unapproved item and returns json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('roadmap.suggest'), [
            'suggestion_title' => 'Dark mode for displays',
            'suggestion_description' => 'Our lobby is dim.',
        ])
        ->assertOk()
        ->assertJson(['sent' => true]);

    $item = RoadmapItem::where('title', 'Dark mode for displays')->first();
    expect($item)->not->toBeNull()
        ->and($item->is_approved)->toBeFalse()
        ->and($item->submitted_by_user_id)->toBe($user->id);
});

test('suggesting a feature as json returns validation errors', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('roadmap.suggest'), ['suggestion_title' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('suggestion_title');
});

test('suggesting a feature from the blade form still redirects back with a flash', function () {
    $this->actingAs(User::factory()->create())
        ->from('/dashboard')
        ->post(route('roadmap.suggest'), ['suggestion_title' => 'Dark mode for displays'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('suggestion_sent', true);
});

test('voting returns the new vote state as json and cannot vote on unapproved items', function () {
    $user = User::factory()->create();
    $approved = RoadmapItem::create(['title' => 'Dark mode', 'status' => 'planned', 'is_approved' => true]);
    $pending = RoadmapItem::create(['title' => 'Pending idea', 'status' => 'considering', 'is_approved' => false]);

    $this->actingAs($user)
        ->postJson(route('roadmap.vote', ['roadmapItem' => $approved]))
        ->assertOk()
        ->assertExactJson(['voted' => true, 'votes_count' => 1]);

    $this->actingAs($user)
        ->postJson(route('roadmap.vote', ['roadmapItem' => $pending]))
        ->assertNotFound();
});
