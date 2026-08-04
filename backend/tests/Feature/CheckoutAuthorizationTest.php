<?php

use App\Enums\WorkspaceRole;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'settings.cloud_hosted_pro_plan_id' => '12345',
        'lemon-squeezy.api_key' => 'test-key',
        'lemon-squeezy.store' => 'teststore',
    ]);
});

/**
 * Only the workspace owner pays. A member clicking Upgrade must not be able to put a
 * subscription on someone else's workspace, and must not be shown a button that 403s.
 */

/**
 * @return array{0: Workspace, 1: User, 2: User}
 */
function payableWorkspace(): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $member = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    return [$workspace, $owner, $member];
}

test('the owner can start a checkout', function () {
    Http::fake([
        '*lemonsqueezy.com/v1/checkouts*' => Http::response([
            'data' => ['attributes' => ['url' => 'https://teststore.lemonsqueezy.com/checkout/x']],
        ]),
    ]);

    [$workspace, $owner] = payableWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.checkout'))
        ->assertRedirect('https://teststore.lemonsqueezy.com/checkout/x');
});

test('a member cannot start a checkout', function () {
    Http::fake();

    [$workspace, , $member] = payableWorkspace();

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.checkout'))->assertForbidden();

    // And no checkout was created at Lemon Squeezy either.
    Http::assertNothingSent();
});

test('an admin cannot start a checkout', function () {
    Http::fake();

    [$workspace, , $member] = payableWorkspace();

    WorkspaceMember::where('workspace_id', $workspace->id)
        ->where('user_id', $member->id)
        ->update(['role' => WorkspaceRole::ADMIN]);

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.checkout'))->assertForbidden();
    Http::assertNothingSent();
});

test('checkout is unavailable on a self-hosted instance', function () {
    Http::fake();
    config(['settings.is_self_hosted' => true]);

    [$workspace, $owner] = payableWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.checkout'))->assertNotFound();
});

test('the dashboard tells a member to ask the owner, and calls no API while rendering', function () {
    Http::fake();

    [$workspace, , $member] = payableWorkspace();

    // A display makes the workspace exceed the free allowance, which is what surfaces the
    // upgrade prompt.
    Display::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ask the owner of this workspace');

    // The old blade built a Checkout during rendering, which POSTed to Lemon Squeezy on
    // every dashboard view for every non-Pro user.
    Http::assertNothingSent();
});

test('the dashboard shows the owner a real checkout button without calling the API', function () {
    Http::fake();

    [$workspace, $owner] = payableWorkspace();
    Display::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('billing.checkout'), false);

    Http::assertNothingSent();
});
