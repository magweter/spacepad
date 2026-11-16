<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\InstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{0: Workspace, 1: User, 2: User}
 */
function billingPageWorkspace(array $attributes = []): array
{
    $workspace = Workspace::factory()->create(['name' => 'Playup', ...$attributes]);
    $owner = User::factory()->active()->create(['name' => 'Olga Owner']);
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    return [$workspace, $owner, $member];
}

function billingPageAs(Workspace $workspace, User $user)
{
    test()->actingAs($user);
    session()->put('selected_workspace_id', $workspace->id);

    return test()->get(route('billing.index'));
}

test('an owner of a free cloud workspace is offered the checkout, without calling Lemon Squeezy', function () {
    config(['settings.is_self_hosted' => false]);
    Http::fake();
    [$workspace, $owner] = billingPageWorkspace();

    billingPageAs($workspace, $owner)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Billing/Index')
            ->where('shell.nav.6.active', true)
            ->where('billing.canManage', true)
            ->where('billing.hasPro', false)
            ->where('billing.state', 'checkout')
            ->where('billing.cancel', null)
            ->where('license', null)
            ->where('urls.checkout', route('billing.checkout')));

    Http::assertNothingSent();
});

test('a member cannot pay and is told who to ask', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, , $member] = billingPageWorkspace();

    billingPageAs($workspace, $member)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billing.canManage', false)
            ->where('billing.state', 'ask')
            ->has('billing.managers', 1)
            ->where('billing.managers.0.name', 'Olga Owner'));
});

test('a self-hosted instance shows its license instead of a checkout', function () {
    config(['settings.is_self_hosted' => true]);
    [$workspace, $owner] = billingPageWorkspace();

    billingPageAs($workspace, $owner)
        ->assertInertia(fn (Assert $page) => $page
            ->where('billing.selfHosted', true)
            ->where('billing.monthlyCost', null)
            ->where('license.active', false)
            ->where('urls.license', route('license.validate')));

    InstanceService::storeInstanceVariable('license_key', 'KEY-1');
    InstanceService::storeInstanceVariable('license_valid', '1');
    InstanceService::storeInstanceVariable('license_expires_at', now()->addYear()->toDateTimeString());

    billingPageAs($workspace, $owner)
        ->assertInertia(fn (Assert $page) => $page
            ->where('license.active', true)
            ->where('license.expiresOn', now()->addYear()->format('j F Y')));
});

test('a billing action that cannot go ahead returns to the billing page', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, $owner] = billingPageWorkspace();
    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-monthly'))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('error');
});
