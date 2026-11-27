<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->admin = User::factory()->active()->create(['is_admin' => true, 'name' => 'Admin Person']);
});

test('non-admins and impersonating admins cannot reach the user screens', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.users.show', $this->admin))->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.impersonate', $this->admin))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.users.delete', $this->admin), ['confirm_email' => $this->admin->email])->assertForbidden();

    $this->actingAs($this->admin)->withSession(['impersonating' => true])->get(route('admin.index'))->assertForbidden();
});

test('the users overview lists every column the table shows', function () {
    $user = User::factory()->active()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->actingAs($this->admin)->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users')
            ->where('search', '')
            ->where('pagination.total', 2)
            ->where('pagination.currentPage', 1)
            ->where('urls.index', route('admin.index'))
            ->where('users', function ($users) use ($user) {
                $row = collect($users)->firstWhere('email', 'ada@example.com');

                return $row['name'] === 'Ada Lovelace'
                    && array_key_exists('usageType', $row)
                    && $row['displays'] === 0
                    && $row['boards'] === 0
                    && $row['plan'] === ['key' => 'free', 'label' => 'Free']
                    && $row['registered'] === $user->created_at->format('Y-m-d')
                    && $row['urls']['show'] === route('admin.users.show', $user)
                    && $row['urls']['impersonate'] === route('admin.users.impersonate', $user);
            }));
});

test('the users overview searches by name or email and keeps the search in the page links', function () {
    User::factory()->active()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->active()->create(['name' => 'Grace Hopper', 'email' => 'grace@navy.example']);

    $this->actingAs($this->admin)->get(route('admin.index', ['search' => 'navy']))
        ->assertInertia(fn (Assert $page) => $page->where('search', 'navy')
            ->has('users', 1)
            ->where('users.0.name', 'Grace Hopper'));

    $this->actingAs($this->admin)->get(route('admin.index', ['search' => 'Lovelace']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 1)->where('users.0.email', 'ada@example.com'));

    $this->actingAs($this->admin)->get(route('admin.index', ['search' => 'nobody']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 0));

    User::factory()->count(55)->active()->create(['name' => 'Bulk Navy']);

    $this->actingAs($this->admin)->get(route('admin.index', ['search' => 'Navy']))
        ->assertInertia(fn (Assert $page) => $page->has('users', 50)
            ->where('pagination.lastPage', 2)
            ->where('pagination.total', 56)
            ->where('pagination.next', fn ($url) => str_contains($url, 'search=Navy') && str_contains($url, 'page=2')));
});

test('the old roadmap tab link lands on the roadmap screen', function () {
    $this->actingAs($this->admin)->get(route('admin.index', ['tab' => 'roadmap']))
        ->assertRedirect(route('admin.roadmap.index'));

    $this->actingAs($this->admin)->get(route('admin.index', ['tab' => 'users']))
        ->assertOk();
});

test('the user page shows the account, the data summary and the actions', function () {
    $user = User::factory()->active()->create(['email' => 'ada@example.com']);

    $this->actingAs($this->admin)->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/User')
            ->where('user.email', 'ada@example.com')
            ->where('user.id', $user->id)
            ->where('user.lastActivity', $user->last_activity_at?->format('Y-m-d H:i:s'))
            ->where('user.isSelf', false)
            ->where('counts.workspaces', 1)
            ->where('counts.displays', 0)
            ->has('counts.outlookAccounts')
            ->has('counts.googleAccounts')
            ->has('counts.caldavAccounts')
            ->has('counts.devices')
            ->where('subscription', null)
            ->has('billingChanges', 0)
            ->where('billingWorkspace.name', $user->primaryWorkspace()->name)
            ->where('urls.delete', route('admin.users.delete', $user))
            ->where('urls.impersonate', route('admin.users.impersonate', $user)));

    $this->actingAs($this->admin)->get(route('admin.users.show', $this->admin))
        ->assertInertia(fn (Assert $page) => $page->where('user.isSelf', true));
});

test('deleting a user needs their email typed exactly', function () {
    $user = User::factory()->active()->create(['email' => 'ada@example.com']);

    $this->actingAs($this->admin)->from(route('admin.users.show', $user))
        ->delete(route('admin.users.delete', $user), [])
        ->assertSessionHasErrors('confirm_email');

    $this->actingAs($this->admin)->from(route('admin.users.show', $user))
        ->delete(route('admin.users.delete', $user), ['confirm_email' => 'someone@example.com'])
        ->assertRedirect(route('admin.users.show', $user))
        ->assertSessionHasErrors(['confirm_email' => 'Email confirmation does not match.']);

    // The deletion itself (and its redirect to the users list) is covered by
    // WorkspaceDataRetentionTest, which sets up the cloud-only billing tables it touches.
    expect(User::find($user->id))->not->toBeNull();
});

test('an admin cannot delete or impersonate themselves', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.users.delete', $this->admin), ['confirm_email' => $this->admin->email])
        ->assertRedirect(route('admin.index'))
        ->assertSessionHas('error', 'You cannot delete your own account.');

    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $this->admin))
        ->assertRedirect(route('admin.index'))
        ->assertSessionHas('error', 'You cannot impersonate yourself.');
});

test('impersonating reloads the whole page and stopping returns to the admin panel', function () {
    $user = User::factory()->active()->create(['email' => 'ada@example.com']);

    // From the Inertia users list: a full page load, so the banner in the root view is drawn.
    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $user), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'))
        ->assertSessionHas('success', 'You are now impersonating ada@example.com');

    $this->assertAuthenticatedAs($user);
    expect(session('impersonating'))->toBeTrue();
    expect(session('impersonator_id'))->toBe($this->admin->id);

    // The banner's plain form post.
    $this->post(route('admin.stop-impersonating'))
        ->assertRedirect(route('admin.index'))
        ->assertSessionHas('success', 'Stopped impersonating user.');

    $this->assertAuthenticatedAs($this->admin);
    expect(session('impersonating'))->toBeNull();
});

test('a plain form post to impersonate still redirects to the dashboard', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $user))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
