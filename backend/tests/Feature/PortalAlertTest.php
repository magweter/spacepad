<?php

use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\PortalAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Which alerts a workspace sees: only active ones, only for its own providers, never on a
 * self-hosted instance, and an ended alert disappears even while the list is cached.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->workspace = User::factory()->active()->create()->primaryWorkspace();
});

test('an active alert for everyone shows for any workspace', function () {
    PortalAlert::factory()->create(['title' => 'Microsoft 365 outage']);

    $alerts = PortalAlert::forWorkspace($this->workspace);

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['title'])->toBe('Microsoft 365 outage')
        ->and($alerts[0]['tone'])->toBe('attention');
});

test('scheduled and ended alerts do not show', function () {
    PortalAlert::factory()->scheduled()->create();
    PortalAlert::factory()->ended()->create();

    expect(PortalAlert::forWorkspace($this->workspace))->toBe([]);
});

test('an alert that ends shows no longer, even from the cache', function () {
    PortalAlert::factory()->create(['ends_at' => now()->addMinutes(5)]);

    expect(PortalAlert::forWorkspace($this->workspace))->toHaveCount(1);

    $this->travel(6)->minutes();

    expect(PortalAlert::forWorkspace($this->workspace))->toBe([]);
});

test('a scheduled alert shows once it starts, even from the cache', function () {
    PortalAlert::factory()->create(['starts_at' => now()->addSeconds(30)]);

    expect(PortalAlert::forWorkspace($this->workspace))->toBe([]);

    $this->travel(40)->seconds();

    expect(PortalAlert::forWorkspace($this->workspace))->toHaveCount(1);
});

test('a provider alert shows only for workspaces with that provider', function () {
    PortalAlert::factory()->forProvider('microsoft')->create();

    $withMicrosoft = $this->workspace;
    OutlookAccount::factory()->create(['workspace_id' => $withMicrosoft->id]);

    // Not ->active(): that state gives every user a Microsoft account.
    $googleOnly = User::factory()->create()->primaryWorkspace();
    GoogleAccount::factory()->create(['workspace_id' => $googleOnly->id]);

    expect(PortalAlert::forWorkspace($withMicrosoft))->toHaveCount(1)
        ->and(PortalAlert::forWorkspace($googleOnly))->toBe([]);
});

test('nothing shows on a self-hosted instance', function () {
    PortalAlert::factory()->create();
    config(['settings.is_self_hosted' => true]);

    expect(PortalAlert::forWorkspace($this->workspace))->toBe([]);
});

test('without a workspace there are no alerts', function () {
    PortalAlert::factory()->create();

    expect(PortalAlert::forWorkspace(null))->toBe([]);
});
