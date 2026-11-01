<?php

use App\Enums\WorkspaceRole;
use App\Listeners\CancelSupersededSubscription;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LemonSqueezy\Laravel\Events\SubscriptionCreated;
use LemonSqueezy\Laravel\Subscription;

uses(RefreshDatabase::class);

/**
 * A monthly customer moves to yearly through a fresh checkout, where they are present to
 * pay and to pass 3D Secure. Only once that checkout has produced the yearly subscription
 * is the monthly one cancelled.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'settings.pro_switch_product_id' => '10',
        'settings.pro_switch_monthly_variant_id' => '11',
        'settings.pro_switch_yearly_variant_id' => '12',
        'settings.pro_yearly_variant_ids' => [],
        'lemon-squeezy.api_key' => 'test-key',
        'lemon-squeezy.store' => 'teststore',
    ]);

    cache()->flush();
});

/**
 * @return array{0: Workspace, 1: User, 2: User}
 */
function switchingWorkspace(?string $variant = '1', string $status = 'active'): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER]);

    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    if ($variant !== null) {
        addSubscription($workspace, 'sub_monthly', $variant, $status, now()->subMonth());
    }

    return [$workspace, $owner, $member];
}

function addSubscription(Workspace $workspace, string $id, string $variant, string $status = 'active', $createdAt = null, $endsAt = null): Subscription
{
    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => $id,
        'status' => $status,
        'product_id' => '1',
        'variant_id' => $variant,
        'ends_at' => $endsAt,
        'created_at' => $createdAt ?? now(),
        'updated_at' => now(),
    ]);

    return Subscription::where('lemon_squeezy_id', $id)->first();
}

function fakeSwitchCheckout(): void
{
    Http::fake([
        '*lemonsqueezy.com/v1/checkouts*' => Http::response([
            'data' => ['attributes' => ['url' => 'https://teststore.lemonsqueezy.com/checkout/yearly']],
        ]),
    ]);
}

function cancelledAttributes(string $variant): array
{
    return ['data' => ['attributes' => [
        'status' => 'cancelled', 'product_id' => 1, 'variant_id' => $variant,
        'renews_at' => null, 'ends_at' => now()->addWeeks(2)->toIso8601String(), 'trial_ends_at' => null,
    ]]];
}

test('the owner is sent to a yearly checkout for the current usage', function () {
    fakeSwitchCheckout();
    [$workspace, $owner] = switchingWorkspace();
    Display::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-yearly'))
        ->assertRedirect('https://teststore.lemonsqueezy.com/checkout/yearly');

    // Creating the displays pushes usage on its own; only the checkout matters here.
    Http::assertSent(function ($request) use ($workspace) {
        if (! str_ends_with($request->url(), '/checkouts')) {
            return false;
        }

        $data = $request['data'];

        return $data['relationships']['variant']['data']['id'] === '12'
            && $data['relationships']['store']['data']['id'] === 'teststore'
            && $data['attributes']['checkout_data']['variant_quantities'] === [['variant_id' => 12, 'quantity' => 3]]
            && $data['attributes']['checkout_data']['custom']['billable_id'] === (string) $workspace->id
            && ! array_key_exists('discount_code', $data['attributes']['checkout_data'])
            && ! isset($data['attributes']['custom_price']);
    });
});

test('a workspace without usage is still checked out for one unit', function () {
    fakeSwitchCheckout();
    [$workspace, $owner] = switchingWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-yearly'));

    Http::assertSent(fn ($request) => $request['data']['attributes']['checkout_data']['variant_quantities'][0]['quantity'] === 1);
});

test('a member cannot switch to yearly', function () {
    Http::fake();
    [$workspace, , $member] = switchingWorkspace();

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-yearly'))->assertForbidden();
    Http::assertNothingSent();
});

test('a workspace that cannot switch is sent back without a checkout', function (Closure $arrange) {
    Http::fake();
    [$workspace, $owner] = switchingWorkspace();
    $arrange($workspace);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-yearly'))
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('error');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/checkouts'));
})->with([
    'already yearly' => [fn (Workspace $w) => Subscription::query()->update(['variant_id' => '12'])],
    'no subscription' => [fn (Workspace $w) => Subscription::query()->delete()],
    'cancelled' => [fn (Workspace $w) => Subscription::query()->update(['status' => 'cancelled', 'ends_at' => now()->addWeek()])],
    'manually billed' => [fn (Workspace $w) => $w->update(['is_manually_billed' => true])],
    'unlimited' => [fn (Workspace $w) => $w->update(['is_unlimited' => true])],
    'not configured' => [fn (Workspace $w) => config(['settings.pro_switch_yearly_variant_id' => null])],
]);

test('a failing checkout leaves the owner on the page with an explanation', function () {
    Http::fake(['*' => Http::response([], 500)]);
    [$workspace, $owner] = switchingWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-yearly'))
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('error');
});

test('a new yearly subscription cancels the monthly one it replaces', function () {
    Http::fake(['*lemonsqueezy.com/v1/subscriptions/sub_monthly' => Http::response(cancelledAttributes('1'))]);
    [$workspace] = switchingWorkspace();
    $yearly = addSubscription($workspace, 'sub_yearly', '12');

    app(CancelSupersededSubscription::class)->handle(new SubscriptionCreated($workspace, $yearly, []));

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), 'subscriptions/sub_monthly'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sub_yearly'));
    expect(Subscription::where('lemon_squeezy_id', 'sub_monthly')->value('status'))->toBe('cancelled');
});

test('subscriptions that are already cancelled or expired are left alone', function () {
    Http::fake();
    [$workspace] = switchingWorkspace(status: 'cancelled');
    Subscription::where('lemon_squeezy_id', 'sub_monthly')->update(['ends_at' => now()->addWeek()]);
    addSubscription($workspace, 'sub_old', '1', 'expired', now()->subYear(), now()->subMonth());
    $yearly = addSubscription($workspace, 'sub_yearly', '12');

    app(CancelSupersededSubscription::class)->handle(new SubscriptionCreated($workspace, $yearly, []));

    Http::assertNothingSent();
});

test('a new monthly subscription cancels nothing', function () {
    Http::fake();
    [$workspace] = switchingWorkspace();
    $monthly = addSubscription($workspace, 'sub_new', '1');

    app(CancelSupersededSubscription::class)->handle(new SubscriptionCreated($workspace, $monthly, []));

    Http::assertNothingSent();
});

test('a refused cancellation is reported rather than thrown', function () {
    Http::fake(['*' => Http::response([], 500)]);
    [$workspace] = switchingWorkspace();
    $yearly = addSubscription($workspace, 'sub_yearly', '12');

    app(CancelSupersededSubscription::class)->handle(new SubscriptionCreated($workspace, $yearly, []));

    expect(Subscription::where('lemon_squeezy_id', 'sub_monthly')->value('status'))->toBe('active');
});

test('the listener is registered for the webhook event', function () {
    Event::fake();

    Event::assertListening(SubscriptionCreated::class, CancelSupersededSubscription::class);
});
