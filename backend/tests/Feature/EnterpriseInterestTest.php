<?php

use App\Enums\WorkspaceRole;
use App\Models\FunnelEvent;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Notifications\EnterpriseInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false, 'settings.sales_email' => 'sales@example.test', 'settings.cloud_hosted_pro_plan_id' => 'variant-1']);
    Notification::fake();
});

test('an owner choosing enterprise tells sales once and goes on to the trial', function () {
    $owner = User::factory()->active()->create(['name' => 'Sanne Vos']);
    $workspace = $owner->primaryWorkspace();
    $workspace->forceFill(['is_unlimited' => true])->save();

    // A workspace that already has Pro skips the checkout.
    $this->actingAs($owner)->post(route('billing.enterprise'))->assertRedirect(route('billing.index'));
    $this->actingAs($owner)->post(route('billing.enterprise'));

    Notification::assertSentOnDemandTimes(EnterpriseInterest::class, 1);
    Notification::assertSentOnDemand(EnterpriseInterest::class, fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'sales@example.test'
        && $n->requesterName === 'Sanne Vos' && $n->workspaceName === $workspace->name);
    expect(FunnelEvent::where('event', 'enterprise_interest')->count())->toBe(1);
});

test('a member cannot ask for enterprise on behalf of the workspace', function () {
    $owner = User::factory()->active()->create();
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $owner->primaryWorkspace()->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    $this->actingAs($member)->withSession(['selected_workspace_id' => $owner->primaryWorkspace()->id])
        ->post(route('billing.enterprise'))->assertForbidden();

    Notification::assertNothingSent();
});
