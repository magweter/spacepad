<?php

use App\Enums\RoadmapStatus;
use App\Models\RoadmapItem;
use App\Models\RoadmapVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The admin roadmap: the public items, the suggestions that wait for approval, and creating,
 * editing, approving and deleting them.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->admin = User::factory()->active()->create(['is_admin' => true]);
});

function roadmapItem(array $attributes = []): RoadmapItem
{
    return RoadmapItem::create([
        'title' => 'An item',
        'status' => RoadmapStatus::Planned,
        'is_approved' => true,
        ...$attributes,
    ]);
}

test('the roadmap page splits pending suggestions from the public roadmap', function () {
    $submitter = User::factory()->active()->create(['name' => 'Sam Submitter', 'email' => 'sam@example.test']);
    $suggestion = roadmapItem(['title' => 'Dark mode', 'description' => str_repeat('a', 150), 'is_approved' => false, 'submitted_by_user_id' => $submitter->id]);
    $public = roadmapItem(['title' => 'CalDAV sync', 'status' => RoadmapStatus::Building, 'expected_at' => '2026-12-01']);
    RoadmapVote::create(['roadmap_item_id' => $public->id, 'user_id' => $submitter->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.roadmap.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Roadmap')
            ->where('pendingRoadmap', 1)
            ->has('pending', 1)
            ->where('pending.0.title', 'Dark mode')
            ->where('pending.0.description', str_repeat('a', 100).'...')
            ->where('pending.0.submittedBy', ['name' => 'Sam Submitter', 'email' => 'sam@example.test'])
            ->where('pending.0.date', $suggestion->created_at->format('d M Y'))
            ->where('pending.0.approveUrl', route('admin.roadmap.approve', $suggestion))
            ->where('pending.0.editUrl', route('admin.roadmap.edit', $suggestion))
            ->where('pending.0.deleteUrl', route('admin.roadmap.destroy', $suggestion))
            ->has('approved', 1)
            ->where('approved.0.title', 'CalDAV sync')
            ->where('approved.0.status', ['value' => 'building', 'label' => 'Building now'])
            ->where('approved.0.expected', 'Dec 2026')
            ->where('approved.0.votes', 1)
            ->where('approved.0.editUrl', route('admin.roadmap.edit', $public))
            ->where('approved.0.deleteUrl', route('admin.roadmap.destroy', $public))
            ->where('urls.create', route('admin.roadmap.create')));
});

test('the public roadmap is ordered by status, then sort order', function () {
    roadmapItem(['title' => 'Shipped one', 'status' => RoadmapStatus::Shipped]);
    roadmapItem(['title' => 'Planned later', 'status' => RoadmapStatus::Planned, 'sort_order' => 5]);
    roadmapItem(['title' => 'Planned first', 'status' => RoadmapStatus::Planned, 'sort_order' => 1]);
    roadmapItem(['title' => 'Building', 'status' => RoadmapStatus::Building]);

    $titles = collect($this->actingAs($this->admin)->get(route('admin.roadmap.index'))
        ->viewData('page')['props']['approved'])->pluck('title')->all();

    expect($titles)->toBe(['Building', 'Planned first', 'Planned later', 'Shipped one']);
});

test('the create and edit forms get the statuses and the item', function () {
    $item = roadmapItem(['title' => 'Edit me', 'description' => 'Details', 'status' => RoadmapStatus::Shipped, 'expected_at' => '2026-03-15', 'sort_order' => 3]);

    $this->actingAs($this->admin)
        ->get(route('admin.roadmap.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/RoadmapForm')
            ->where('item', null)
            ->where('defaultStatus', 'considering')
            ->has('statuses', count(RoadmapStatus::cases()))
            ->where('statuses.0', ['value' => 'considering', 'label' => 'Under consideration'])
            ->where('urls.submit', route('admin.roadmap.store'))
            ->where('urls.index', route('admin.roadmap.index')));

    $this->actingAs($this->admin)
        ->get(route('admin.roadmap.edit', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/RoadmapForm')
            ->where('item.title', 'Edit me')
            ->where('item.description', 'Details')
            ->where('item.status', 'shipped')
            ->where('item.expected_at', '2026-03-15')
            ->where('item.sort_order', 3)
            ->where('urls.submit', route('admin.roadmap.update', $item)));
});

test('an admin creates an approved item and lands back on the roadmap', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roadmap.store'), ['title' => 'New thing', 'status' => 'planned', 'sort_order' => 2])
        ->assertRedirect(route('admin.roadmap.index'))
        ->assertSessionHas('success', 'Item created.');

    $item = RoadmapItem::firstWhere('title', 'New thing');
    expect($item->is_approved)->toBeTrue()
        ->and($item->status)->toBe(RoadmapStatus::Planned)
        ->and($item->sort_order)->toBe(2);
});

test('an admin updates an item', function () {
    $item = roadmapItem();

    $this->actingAs($this->admin)
        ->put(route('admin.roadmap.update', $item), ['title' => 'Renamed', 'status' => 'shipped', 'expected_at' => '2026-05-01'])
        ->assertRedirect(route('admin.roadmap.index'))
        ->assertSessionHas('success', 'Item updated.');

    expect($item->fresh()->title)->toBe('Renamed')
        ->and($item->fresh()->status)->toBe(RoadmapStatus::Shipped);
});

test('the form is validated', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roadmap.store'), ['title' => '', 'status' => 'nope', 'expected_at' => 'not a date', 'sort_order' => -1])
        ->assertSessionHasErrors(['title', 'status', 'expected_at', 'sort_order']);

    $this->actingAs($this->admin)
        ->put(route('admin.roadmap.update', roadmapItem()), ['title' => str_repeat('x', 151), 'status' => 'planned'])
        ->assertSessionHasErrors(['title']);

    expect(RoadmapItem::count())->toBe(1);
});

test('an admin approves a suggestion and deletes an item', function () {
    $suggestion = roadmapItem(['is_approved' => false]);
    $item = roadmapItem();

    $this->actingAs($this->admin)
        ->from(route('admin.roadmap.index'))
        ->post(route('admin.roadmap.approve', $suggestion))
        ->assertRedirect(route('admin.roadmap.index'))
        ->assertSessionHas('success', 'Suggestion approved and now visible.');

    expect($suggestion->fresh()->is_approved)->toBeTrue();

    $this->actingAs($this->admin)
        ->from(route('admin.roadmap.index'))
        ->delete(route('admin.roadmap.destroy', $item))
        ->assertRedirect(route('admin.roadmap.index'))
        ->assertSessionHas('success', 'Item deleted.');

    expect(RoadmapItem::find($item->id))->toBeNull();
});

test('a non-admin cannot reach any roadmap admin action', function () {
    $user = User::factory()->active()->create();
    $item = roadmapItem(['is_approved' => false]);

    $this->actingAs($user)->get(route('admin.roadmap.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.roadmap.create'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.roadmap.edit', $item))->assertForbidden();
    $this->actingAs($user)->post(route('admin.roadmap.store'), ['title' => 'x', 'status' => 'planned'])->assertForbidden();
    $this->actingAs($user)->put(route('admin.roadmap.update', $item), ['title' => 'x', 'status' => 'planned'])->assertForbidden();
    $this->actingAs($user)->post(route('admin.roadmap.approve', $item))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.roadmap.destroy', $item))->assertForbidden();

    expect($item->fresh()->is_approved)->toBeFalse();
});
