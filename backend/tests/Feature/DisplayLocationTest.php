<?php

use App\Enums\DisplayStatus;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->unlimited()->create(); // Pro
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);

    $this->display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
        'name' => 'Boardroom tablet',
        'display_name' => 'Boardroom',
    ]);
});

function updateDisplayInfo(array $fields)
{
    return test()->actingAs(test()->user)->put(route('displays.update', test()->display), [
        'name' => 'Boardroom tablet',
        'display_name' => 'Boardroom',
        ...$fields,
    ]);
}

test('a display has no location or seat count until one is set', function () {
    expect($this->display->location)->toBeNull()
        ->and($this->display->capacity)->toBeNull();
});

test('the location and seat count can be set with the names', function () {
    updateDisplayInfo(['location' => 'Floor 1', 'capacity' => '12'])
        ->assertRedirect(route('displays.configure', $this->display));

    $fresh = $this->display->fresh();

    expect($fresh->location)->toBe('Floor 1')
        ->and($fresh->capacity)->toBe(12);
});

test('emptying the fields clears them', function () {
    $this->display->update(['location' => 'Floor 1', 'capacity' => 12]);

    updateDisplayInfo(['location' => '', 'capacity' => '']);

    $fresh = $this->display->fresh();

    expect($fresh->location)->toBeNull()
        ->and($fresh->capacity)->toBeNull();
});

test('a seat count must be a positive whole number', function (string $capacity) {
    updateDisplayInfo(['capacity' => $capacity])->assertSessionHasErrors('capacity');

    expect($this->display->fresh()->capacity)->toBeNull();
})->with(['0', '-3', 'twelve', '1000']);

test('a location longer than fits under the room name is rejected', function () {
    updateDisplayInfo(['location' => str_repeat('a', 65)])->assertSessionHasErrors('location');
});

test('the configure page shows the current values', function () {
    $this->display->update(['location' => 'Floor 1', 'capacity' => 12]);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertOk()
        ->assertSee('name="location"', false)
        ->assertSee('value="Floor 1"', false)
        ->assertSee('value="12"', false);
});

test('the location and seat count reach the tablet', function () {
    $device = Device::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'display_id' => $this->display->id,
    ]);

    $display = $this->actingAs($device)->getJson('/api/displays')->assertOk()->json('data.0');
    expect($display['location'])->toBeNull()
        ->and($display['capacity'])->toBeNull();

    $this->display->update(['location' => 'Floor 1', 'capacity' => 12]);

    $display = $this->actingAs($device)->getJson('/api/displays')->assertOk()->json('data.0');
    expect($display['location'])->toBe('Floor 1')
        ->and($display['capacity'])->toBe(12);
});
