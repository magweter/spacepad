<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'lemon-squeezy.api_key' => 'test-key',
        'lemon-squeezy.store' => 'teststore',
    ]);
});

/**
 * The billing portal is where a paying workspace changes its payment method, picks up an
 * invoice or cancels. Owners and admins get there, and only when there is a Lemon Squeezy
 * customer behind the workspace to open one for.
 */

/**
 * A workspace with an active subscription, its owner and an ordinary member.
 *
 * @return array{0: Workspace, 1: User, 2: User}
 */
function portalWorkspace(): array
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

    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_1',
        'status' => 'active',
        'product_id' => '1',
        'variant_id' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$workspace, $owner, $member];
}

function givePortalCustomer(Workspace $workspace): void
{
    $workspace->customer()->create(['lemon_squeezy_id' => 'cust_1']);
}

function fakePortalUrl(): void
{
    Http::fake([
        '*lemonsqueezy.com/v1/customers*' => Http::response([
            'data' => ['attributes' => ['urls' => [
                'customer_portal' => 'https://teststore.lemonsqueezy.com/billing?token=x',
            ]]],
        ]),
    ]);
}

test('the owner is sent to the customer portal', function () {
    fakePortalUrl();

    [$workspace, $owner] = portalWorkspace();
    givePortalCustomer($workspace);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.portal'))
        ->assertRedirect('https://teststore.lemonsqueezy.com/billing?token=x');
});

test('an admin can open the billing portal too', function () {
    fakePortalUrl();

    [$workspace, , $member] = portalWorkspace();
    givePortalCustomer($workspace);

    WorkspaceMember::where('workspace_id', $workspace->id)
        ->where('user_id', $member->id)
        ->update(['role' => WorkspaceRole::ADMIN]);

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.portal'))
        ->assertRedirect('https://teststore.lemonsqueezy.com/billing?token=x');
});

test('a member cannot open the billing portal', function () {
    Http::fake();

    [$workspace, , $member] = portalWorkspace();
    givePortalCustomer($workspace);

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.portal'))->assertForbidden();

    Http::assertNothingSent();
});

test('a manually billed workspace has no portal and keeps its support copy', function () {
    Http::fake();

    [$workspace, $owner] = portalWorkspace();
    givePortalCustomer($workspace);
    $workspace->update(['is_manually_billed' => true]);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.portal'))->assertNotFound();

    // We invoice these workspaces ourselves, so the page still points at support rather
    // than at a portal that would show them nothing.
    $this->get(route('workspaces.members'))
        ->assertOk()
        ->assertSee('We invoice this workspace directly')
        ->assertDontSee(route('billing.portal'), false);

    Http::assertNothingSent();
});

test('a Pro workspace without a customer record falls back to the modal', function () {
    Http::fake();

    [$workspace, $owner] = portalWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.portal'))->assertNotFound();

    $this->get(route('workspaces.members'))
        ->assertOk()
        ->assertSee('Manage subscription')
        ->assertDontSee(route('billing.portal'), false);

    Http::assertNothingSent();
});

test('the portal is unavailable on a self-hosted instance', function () {
    Http::fake();

    [$workspace, $owner] = portalWorkspace();
    givePortalCustomer($workspace);
    config(['settings.is_self_hosted' => true]);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.portal'))->assertNotFound();

    Http::assertNothingSent();
});

test('the workspace page shows the portal button without calling the API', function () {
    Http::fake();

    [$workspace, $owner] = portalWorkspace();
    givePortalCustomer($workspace);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->get(route('workspaces.members'))
        ->assertOk()
        ->assertSee('Open billing portal')
        ->assertSee(route('billing.portal'), false)
        ->assertSee('target="_blank"', false);

    // The portal url is fetched behind the POST route for exactly this reason.
    Http::assertNothingSent();
});

test('a failure at Lemon Squeezy leaves the owner on the workspace page', function () {
    Http::fake([
        '*lemonsqueezy.com/v1/customers*' => Http::response([], 500),
    ]);

    [$workspace, $owner] = portalWorkspace();
    givePortalCustomer($workspace);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.portal'))
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('error');
});
