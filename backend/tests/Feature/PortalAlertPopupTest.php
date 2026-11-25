<?php

use App\Models\PortalAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * An alert marked as a popup opens once for every user; after "Got it" that user sees it as a row only.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->user = User::factory()->active()->create();
});

test('a popup alert asks for a popup until the user dismisses it', function () {
    $alert = PortalAlert::factory()->create(['popup' => true]);
    PortalAlert::factory()->create(['title' => 'Plain row']);

    $alerts = PortalAlert::forUser($this->user);
    expect(collect($alerts)->firstWhere('id', $alert->id)['popup'])->toBeTrue()
        ->and(collect($alerts)->firstWhere('title', 'Plain row')['popup'])->toBeFalse();

    $this->actingAs($this->user)
        ->post(route('alerts.dismiss', $alert))
        ->assertRedirect();

    expect(collect(PortalAlert::forUser($this->user))->firstWhere('id', $alert->id)['popup'])->toBeFalse();
});

test('dismissing is per user', function () {
    $alert = PortalAlert::factory()->create(['popup' => true]);
    $other = User::factory()->active()->create();

    $this->actingAs($this->user)->post(route('alerts.dismiss', $alert));
    $this->actingAs($this->user)->post(route('alerts.dismiss', $alert));

    expect(PortalAlert::forUser($other)[0]['popup'])->toBeTrue();
});

test('the shared alerts carry the popup flag and the dismiss url', function () {
    $alert = PortalAlert::factory()->create(['popup' => true]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.0.popup', true)
            ->where('alerts.0.dismissUrl', route('alerts.dismiss', $alert)));
});

test('a guest cannot dismiss', function () {
    $alert = PortalAlert::factory()->create(['popup' => true]);

    $this->post(route('alerts.dismiss', $alert))->assertRedirect(route('login'));
});
