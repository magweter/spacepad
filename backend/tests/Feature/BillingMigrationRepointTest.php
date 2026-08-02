<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);

    $this->migration = require database_path('migrations/2026_07_30_000022_repoint_lemon_squeezy_billables_to_workspaces.php');
});

/**
 * Insert a subscription addressed to a user, as every pre-cutover row is.
 */
function userSubscription(User $user, int $id = 1, string $lemonId = 'sub_1'): void
{
    DB::table('lemon_squeezy_subscriptions')->insert([
        'id' => $id,
        'billable_id' => $user->id,
        'billable_type' => $user->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => $lemonId,
        'status' => 'active',
        'product_id' => '1',
        'variant_id' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function userCustomer(User $user, int $id = 1, string $lemonId = 'cus_1'): void
{
    DB::table('lemon_squeezy_customers')->insert([
        'id' => $id,
        'billable_id' => $user->id,
        'billable_type' => $user->getMorphClass(),
        'lemon_squeezy_id' => $lemonId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('a subscription moves onto the users workspace', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    userSubscription($user);

    $this->migration->up();

    $row = DB::table('lemon_squeezy_subscriptions')->first();

    expect($row->billable_id)->toBe($workspace->id);
    expect($row->billable_type)->toBe((new Workspace)->getMorphClass());

    // The previous values are kept so down() is an exact restore rather than a guess.
    expect($row->legacy_billable_id)->toBe($user->id);
    expect($row->legacy_billable_type)->toBe($user->getMorphClass());
});

test('down restores the original billable exactly and drops the legacy columns', function () {
    $user = User::factory()->active()->create();
    userSubscription($user);

    $this->migration->up();
    $this->migration->down();

    $row = DB::table('lemon_squeezy_subscriptions')->first();

    expect($row->billable_id)->toBe($user->id);
    expect($row->billable_type)->toBe($user->getMorphClass());
    expect(Schema::hasColumn('lemon_squeezy_subscriptions', 'legacy_billable_id'))->toBeFalse();
});

test('the earliest owned workspace receives the subscription', function () {
    $user = User::factory()->active()->create();
    $first = $user->primaryWorkspace();

    // A second owned workspace, created later.
    $second = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $second->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::OWNER,
        'created_at' => now()->addMinute(),
    ]);

    userSubscription($user);

    $this->migration->up();

    expect(DB::table('lemon_squeezy_subscriptions')->first()->billable_id)->toBe($first->id);
});

test('an explicit billing owner decides the target workspace', function () {
    $user = User::factory()->active()->create();

    $chosen = Workspace::factory()->create(['billing_owner_user_id' => $user->id]);
    WorkspaceMember::create([
        'workspace_id' => $chosen->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::OWNER,
        'created_at' => now()->addMinute(),
    ]);

    userSubscription($user);

    $this->migration->up();

    expect(DB::table('lemon_squeezy_subscriptions')->first()->billable_id)->toBe($chosen->id);
});

test('a user with no billing rows is a no-op', function () {
    User::factory()->active()->create();

    $this->migration->up();

    expect(DB::table('lemon_squeezy_subscriptions')->count())->toBe(0);
});

test('two co-owner customers do not collide, the loser stays on the user', function () {
    $ownerA = User::factory()->active()->create();
    $ownerB = User::factory()->active()->create();

    $workspace = Workspace::factory()->create();

    // Drop the auto-created personal workspaces so the shared one is the only workspace
    // either of them owns — that is what makes both resolve to the same target and collide.
    foreach ([$ownerA, $ownerB] as $owner) {
        WorkspaceMember::where('user_id', $owner->id)->delete();

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
    }

    userCustomer($ownerA, id: 1, lemonId: 'cus_a');
    userCustomer($ownerB, id: 2, lemonId: 'cus_b');

    // Must not throw on the unique (billable_id, billable_type) index.
    $this->migration->up();

    $onWorkspace = DB::table('lemon_squeezy_customers')
        ->where('billable_type', (new Workspace)->getMorphClass())
        ->count();

    expect($onWorkspace)->toBe(1);
    expect(DB::table('lemon_squeezy_customers')->count())->toBe(2);
});

test('the migration does nothing when self-hosted', function () {
    $user = User::factory()->active()->create();
    userSubscription($user);

    config(['settings.is_self_hosted' => true]);

    $this->migration->up();

    expect(DB::table('lemon_squeezy_subscriptions')->first()->billable_type)->toBe($user->getMorphClass());
});
