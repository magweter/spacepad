<?php

use App\Enums\WorkspaceRole;
use App\Models\CalDAVAccount;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * DisplayController::store used to check only that the chosen calendar account existed,
 * not that it belonged to the caller's workspace. A user who knew another tenant's account
 * ULID could create a display on it and read that tenant's calendar through the account's
 * stored OAuth token.
 */
function displayPayload(string $provider, string $accountId): array
{
    return [
        'name' => 'Boardroom',
        'displayName' => 'Boardroom',
        'provider' => $provider,
        'account' => $accountId,
        'room' => 'boardroom@example.com,Boardroom',
    ];
}

test('a user cannot create a display on an outlook account from another workspace', function () {
    $owner = User::factory()->active()->create();
    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $owner->primaryWorkspace()->id,
    ]);

    $attacker = User::factory()->active()->create();

    $this->actingAs($attacker)
        ->post(route('displays.store'), displayPayload('outlook', $account->id))
        ->assertForbidden();

    expect(Display::count())->toBe(0);
    expect(Calendar::where('outlook_account_id', $account->id)->count())->toBe(0);
});

test('a user cannot create a display on a google account from another workspace', function () {
    $owner = User::factory()->active()->create();
    $account = GoogleAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $owner->primaryWorkspace()->id,
    ]);

    $attacker = User::factory()->active()->create();

    $this->actingAs($attacker)
        ->post(route('displays.store'), displayPayload('google', $account->id))
        ->assertForbidden();

    expect(Display::count())->toBe(0);
    expect(Calendar::where('google_account_id', $account->id)->count())->toBe(0);
});

test('a user cannot create a display on a caldav account from another workspace', function () {
    $owner = User::factory()->active()->create();
    $account = CalDAVAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $owner->primaryWorkspace()->id,
    ]);

    $attacker = User::factory()->active()->create();

    $this->actingAs($attacker)
        ->post(route('displays.store'), displayPayload('caldav', $account->id))
        ->assertForbidden();

    expect(Display::count())->toBe(0);
    expect(Calendar::where('caldav_account_id', $account->id)->count())->toBe(0);
});

test('a workspace member can create a display on an account connected by another member', function () {
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();

    $member = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($member)
        ->withSession(['selected_workspace_id' => $workspace->id])
        ->post(route('displays.store'), displayPayload('outlook', $account->id))
        ->assertRedirect(route('dashboard'));

    expect(Display::where('workspace_id', $workspace->id)->count())->toBe(1);
    expect(Calendar::where('outlook_account_id', $account->id)->count())->toBe(1);
});
