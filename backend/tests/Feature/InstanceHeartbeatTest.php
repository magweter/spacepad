<?php

namespace Tests\Feature;

use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('instance heartbeat can include boards_count', function () {
    $response = $this->postJson('/api/v1/instances/heartbeat', [
        'instance_key' => 'test-instance-key',
        'license_key' => null,
        'license_valid' => false,
        'license_expires_at' => null,
        'is_self_hosted' => true,
        'displays_count' => 5,
        'rooms_count' => 2,
        'boards_count' => 3,
        'version' => '1.0.0',
        'users' => [
            [
                'email' => 'test@example.com',
                'usage_type' => 'personal',
            ],
        ],
    ]);

    $response->assertStatus(200);

    $instance = Instance::where('instance_key', 'test-instance-key')->first();
    expect($instance)->not->toBeNull();
    expect($instance->boards_count)->toBe(3);
});

test('instance heartbeat works without boards_count for backward compatibility', function () {
    $response = $this->postJson('/api/v1/instances/heartbeat', [
        'instance_key' => 'test-instance-key-2',
        'license_key' => null,
        'license_valid' => false,
        'license_expires_at' => null,
        'is_self_hosted' => true,
        'displays_count' => 5,
        'rooms_count' => 2,
        'version' => '1.0.0',
        'users' => [
            [
                'email' => 'test@example.com',
                'usage_type' => 'personal',
            ],
        ],
    ]);

    $response->assertStatus(200);

    $instance = Instance::where('instance_key', 'test-instance-key-2')->first();
    expect($instance)->not->toBeNull();
    expect($instance->boards_count)->toBeNull();
});

test('instance heartbeat updates existing instance with boards_count', function () {
    $instance = Instance::factory()->create([
        'instance_key' => 'existing-instance',
        'boards_count' => null,
    ]);

    $response = $this->postJson('/api/v1/instances/heartbeat', [
        'instance_key' => 'existing-instance',
        'license_key' => null,
        'license_valid' => false,
        'license_expires_at' => null,
        'is_self_hosted' => true,
        'displays_count' => 5,
        'rooms_count' => 2,
        'boards_count' => 7,
        'version' => '1.0.0',
        'users' => [
            [
                'email' => 'test@example.com',
                'usage_type' => 'personal',
            ],
        ],
    ]);

    $response->assertStatus(200);

    $instance->refresh();
    expect($instance->boards_count)->toBe(7);
});

/**
 * A heartbeat used to fall back to matching on the users list when the instance key was
 * unknown, and then overwrote the matched row, key included. Anyone who could reproduce
 * another instance's users payload could re-key that instance to their own key and read
 * its license through /validate. The key is the only identity a heartbeat has.
 */
test('a heartbeat with an unknown key never adopts an instance whose users match', function () {
    $users = [['email' => 'owner@example.com', 'usage_type' => 'business']];

    $victim = Instance::factory()->create([
        'instance_key' => 'victim-key',
        'license_key' => 'victim-license',
        'users' => $users,
    ]);

    $this->postJson('/api/v1/instances/heartbeat', [
        'instance_key' => 'attacker-key',
        'license_key' => null,
        'license_valid' => false,
        'license_expires_at' => null,
        'is_self_hosted' => true,
        'displays_count' => 1,
        'rooms_count' => 1,
        'version' => '1.0.0',
        'users' => $users,
    ])->assertStatus(200);

    $victim->refresh();
    expect($victim->instance_key)->toBe('victim-key')
        ->and($victim->license_key)->toBe('victim-license')
        ->and(Instance::where('instance_key', 'attacker-key')->exists())->toBeTrue();
});
