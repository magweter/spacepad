<?php

use App\Models\PortalAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * An alert marked as a popup opens once for every user. "Got it" (or closing its row) is done with
 * the alert for that user: it no longer shows anywhere in their portal.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->user = User::factory()->active()->create();
});

test('a popup alert asks for a popup, and is gone for the user once dismissed', function () {
    $alert = PortalAlert::factory()->create(['popup' => true]);
    PortalAlert::factory()->create(['title' => 'Plain row']);

    $alerts = PortalAlert::forUser($this->user);
    expect(collect($alerts)->firstWhere('id', $alert->id)['popup'])->toBeTrue()
        ->and(collect($alerts)->firstWhere('title', 'Plain row')['popup'])->toBeFalse();

    $this->actingAs($this->user)
        ->post(route('alerts.dismiss', $alert))
        ->assertRedirect();

    $left = collect(PortalAlert::forUser($this->user));
    expect($left->firstWhere('id', $alert->id))->toBeNull()
        ->and($left->pluck('title')->all())->toBe(['Plain row']);
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

test('the shared alerts carry the image url, and the image is served to signed-in users only', function () {
    Storage::fake();
    Storage::put('portal-alerts/new.png', 'png-bytes');
    $alert = PortalAlert::factory()->create(['popup' => true, 'image_path' => 'portal-alerts/new.png']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts.0.image', $alert->imageUrl()));

    $this->actingAs($this->user)->get($alert->imageUrl())->assertOk();
    auth()->logout();
    $this->get($alert->imageUrl())->assertRedirect(route('login'));
});

test('an alert without an image has none', function () {
    PortalAlert::factory()->create();

    expect(PortalAlert::forUser($this->user)[0]['image'])->toBeNull();
});

test('the portal dismisses in the background and gets a plain answer', function () {
    $alert = PortalAlert::factory()->create(['popup' => true]);

    $this->actingAs($this->user)
        ->postJson(route('alerts.dismiss', $alert))
        ->assertOk()
        ->assertJson(['dismissed' => true]);

    expect(PortalAlert::forUser($this->user))->toBe([]);
});

test('an alert that started before the account was made does not show to that account', function () {
    $old = PortalAlert::factory()->create(['title' => 'Live view is here', 'starts_at' => now()->subWeek()]);
    $fresh = User::factory()->active()->create(['created_at' => now()->subDay()]);
    $veteran = User::factory()->active()->create(['created_at' => now()->subMonth()]);

    expect(PortalAlert::forUser($fresh))->toBe([])
        ->and(collect(PortalAlert::forUser($veteran))->pluck('id')->all())->toBe([$old->id]);
});

test('no alerts while someone is in onboarding or setting up the first display', function () {
    PortalAlert::factory()->create(['popup' => true]);
    $newcomer = User::factory()->create(['usage_type' => null, 'email_verified_at' => now(), 'terms_accepted_at' => now()]);
    expect($newcomer->isOnboarded())->toBeFalse();

    $this->actingAs($newcomer)->get(route('onboarding'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts', []));

    $this->actingAs($this->user)->get(route('setup.first-display'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts', []));

    // Once past the first steps, the same alert shows.
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('alerts', 1));
});
