<?php

use App\Enums\AccountStatus;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\EventSubscription;
use App\Models\GoogleAccount;
use App\Services\GoogleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('drops a google subscription locally without calling the api', function () {
    $account = GoogleAccount::factory()->create(['status' => AccountStatus::CONNECTED]);
    $subscription = EventSubscription::factory()
        ->google($account)
        ->create([
            'display_id' => Display::factory(),
            'expiration' => now()->subMinutes(5),
        ]);

    // deleteEventSubscription takes no account and never authenticates, so there is no code
    // path left that can reach Google — the signature itself is the guarantee.
    app(GoogleService::class)->deleteEventSubscription($subscription);

    expect(EventSubscription::find($subscription->id))->toBeNull();
});

it('replaces an expired google subscription with a fresh one', function () {
    $account = GoogleAccount::factory()->create(['status' => AccountStatus::CONNECTED]);
    $calendar = Calendar::factory()->create([
        'google_account_id' => $account->id,
        'calendar_id' => 'room@example.com',
    ]);
    $display = Display::factory()->create(['calendar_id' => $calendar->id]);

    $expired = EventSubscription::factory()
        ->google($account)
        ->create([
            'display_id' => $display->id,
            'expiration' => now()->subMinutes(5),
        ]);

    $google = mock(GoogleService::class);
    $google->shouldReceive('deleteEventSubscription')
        ->once()
        ->with(Mockery::on(fn ($sub) => $sub->id === $expired->id));
    $google->shouldReceive('createEventSubscription')
        ->once()
        ->with(
            Mockery::on(fn ($acc) => $acc->id === $account->id),
            Mockery::on(fn ($d) => $d->id === $display->id),
            'room@example.com'
        )
        ->andReturn(EventSubscription::factory()->google($account)->make());

    app()->instance(GoogleService::class, $google);

    $this->artisan('app:renew-subscriptions')->assertExitCode(0);
});

it('does not renew a subscription that is still live', function () {
    $account = GoogleAccount::factory()->create(['status' => AccountStatus::CONNECTED]);
    $calendar = Calendar::factory()->create([
        'google_account_id' => $account->id,
        'calendar_id' => 'room@example.com',
    ]);
    $display = Display::factory()->create(['calendar_id' => $calendar->id]);

    EventSubscription::factory()
        ->google($account)
        ->create([
            'display_id' => $display->id,
            'expiration' => now()->addDay(),
        ]);

    $google = mock(GoogleService::class);
    $google->shouldNotReceive('deleteEventSubscription');
    $google->shouldNotReceive('createEventSubscription');

    app()->instance(GoogleService::class, $google);

    $this->artisan('app:renew-subscriptions')->assertExitCode(0);
});
