<?php

use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A paired tablet outlives the colleague who paired it.
 *
 * Since `devices.user_id` became nullable provenance, the account that paired a device can
 * be gone while the device keeps working for the workspace. The tablet still calls
 * devices/me on every start, so that response has to survive a null user rather than 500
 * the display into an error screen.
 */
it('serves devices/me for a device whose creator was removed', function () {
    $user = User::factory()->create();
    $display = Display::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'display_id' => $display->id,
    ]);

    $device->forceFill(['user_id' => null])->save();

    Sanctum::actingAs($device);

    $response = $this->getJson('/api/devices/me');

    $response->assertOk();
    expect($response->json('data.user'))->toBeNull();
    expect($response->json('data.id'))->toBe($device->id);
    expect($response->json('data.display.id'))->toBe($display->id);
});
