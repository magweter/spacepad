<?php

use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The admin workspace screens render for the shapes that actually occur in production,
 * including the awkward ones: a workspace nobody is left in, and one with no usage.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->admin = User::factory()->active()->create(['is_admin' => true]);
});

test('the list renders with a mix of workspaces, including one with no members', function () {
    $owner = User::factory()->active()->create();
    Display::factory()->count(2)->create(['workspace_id' => $owner->primaryWorkspace()->id]);
    Board::factory()->create(['workspace_id' => $owner->primaryWorkspace()->id]);

    $abandoned = Workspace::factory()->create(['name' => 'Nobody Left']);

    $workspace = $owner->primaryWorkspace();

    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Workspaces')
            ->where('workspaces.total', Workspace::count())
            ->where('workspaces.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $workspace->id
                // 2 displays + 1 board x 2 = 4 units, read from the counters.
                && $row['name'] === $workspace->name && $row['units'] === 4 && $row['displays'] === 2 && $row['boards'] === 1
                && $row['owner']['url'] === route('admin.users.show', $owner)
                && $row['url'] === route('admin.workspaces.show', $workspace)))
            ->where('workspaces.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Nobody Left'
                && $row['owner'] === null && $row['plan']['label'] === 'Free')));

    expect($abandoned->billingOwner())->toBeNull();
});

test('the detail page renders for a workspace with no members and no usage', function () {
    $abandoned = Workspace::factory()->create(['name' => 'Nobody Left']);

    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.show', $abandoned))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Workspace')
            ->where('workspace.name', 'Nobody Left')
            ->where('workspace.owner', null)
            ->where('workspace.plan', 'Free')
            ->where('usage.total', 0)
            ->where('billing.billableUnits', 1)
            ->where('members', [])
            ->where('urls.index', route('admin.workspaces.index')));
});

test('the list searches by workspace name and by member name or email', function () {
    $alice = User::factory()->active()->create(['name' => 'Alice Searchable', 'email' => 'alice@example.test']);
    Workspace::factory()->create(['name' => 'Acme Rooms']);

    $names = fn ($search) => collect($this->actingAs($this->admin)
        ->get(route('admin.workspaces.index', ['search' => $search]))
        ->assertOk()
        ->viewData('page')['props']['workspaces']['data'])->pluck('name')->all();

    expect($names('Acme'))->toBe(['Acme Rooms'])
        ->and($names('Alice Searchable'))->toBe([$alice->primaryWorkspace()->name])
        ->and($names('alice@example.test'))->toBe([$alice->primaryWorkspace()->name])
        ->and($names('nothing-matches'))->toBe([]);

    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.index', ['search' => 'Acme']))
        ->assertInertia(fn (Assert $page) => $page->where('search', 'Acme'));
});

test('the list pages by 50 and keeps the search in the page links', function () {
    Workspace::factory()->count(51)->create(['name' => 'Paged workspace']);

    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.index', ['search' => 'Paged']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('workspaces.data', 50)
            ->where('workspaces.total', 51)
            ->where('workspaces.last_page', 2)
            ->where('workspaces.next_page_url', fn ($url) => str_contains($url, 'search=Paged') && str_contains($url, 'page=2')));
});

test('the detail page lists the members and their roles with links to them', function () {
    $owner = User::factory()->active()->create(['name' => 'Olga Owner']);

    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.show', $owner->primaryWorkspace()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.owner.name', 'Olga Owner')
            ->where('workspace.owner.url', route('admin.users.show', $owner))
            ->has('members', 1)
            ->where('members.0.name', 'Olga Owner')
            ->where('members.0.role', 'Owner')
            ->where('members.0.url', route('admin.users.show', $owner))
            ->where('urls.billing', route('admin.workspaces.billing', $owner->primaryWorkspace())));
});

test('a non-admin cannot reach the workspace screens', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->get(route('admin.workspaces.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.workspaces.show', $user->primaryWorkspace()))->assertForbidden();
    $this->actingAs($user)
        ->post(route('admin.workspaces.billing', $user->primaryWorkspace()), ['billing_mode' => 'manual'])
        ->assertForbidden();
});

test('saving the billing goes back to the workspace with a confirmation', function () {
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();

    $this->actingAs($this->admin)
        ->from(route('admin.workspaces.show', $workspace))
        ->post(route('admin.workspaces.billing', $workspace), ['billing_mode' => 'unlimited', 'billing_owner_user_id' => $owner->id])
        ->assertRedirect(route('admin.workspaces.show', $workspace))
        ->assertSessionHas('success', 'Billing settings updated.');

    expect($workspace->fresh()->is_unlimited)->toBeTrue();
});
