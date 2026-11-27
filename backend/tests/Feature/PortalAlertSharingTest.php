<?php

use App\Models\PortalAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Every portal page gets the alerts of the selected workspace; pages without a signed-in user get none.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

test('the overview shares the active alerts of the workspace', function () {
    PortalAlert::factory()->create(['title' => 'Microsoft 365 outage']);
    PortalAlert::factory()->ended()->create();
    $user = User::factory()->active()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.title', 'Microsoft 365 outage'));
});

test('a guest page shares no alerts', function () {
    PortalAlert::factory()->create();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('alerts', []));
});
