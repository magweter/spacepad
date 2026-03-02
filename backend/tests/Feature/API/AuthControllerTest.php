<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

it('successfully connects with a valid connect code', function () {
    $user = User::factory()->create();
    $code = $user->getConnectCode();
    $uid = 'test-device-uid-123';
    $name = 'Test Device';

    $response = $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => $uid,
        'name' => $name,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'token',
                'device' => [
                    'id',
                    'name',
                    'user',
                    'display',
                ],
            ],
        ]);

    // Verify device was created
    $device = Device::where('uid', $uid)->first();
    expect($device)->not->toBeNull()
        ->and($device->user_id)->toBe($user->id)
        ->and($device->name)->toBe($name)
        ->and($device->workspace_id)->toBe($user->primaryWorkspace()?->id);
});

it('returns error when connect code is invalid', function () {
    $response = $this->postJson('/api/auth/login', [
        'code' => '999999',
        'uid' => 'test-device-uid-123',
        'name' => 'Test Device',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Code is incorrect.',
            'errors' => [
                'code' => ['incorrect'],
            ],
        ]);
});

it('can only use a connect code once', function () {
    $user = User::factory()->create();
    $code = $user->getConnectCode();
    $uid1 = 'test-device-uid-1';
    $uid2 = 'test-device-uid-2';

    // First use - should succeed
    $response1 = $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => $uid1,
        'name' => 'Device 1',
    ]);

    $response1->assertOk();

    // Second use with same code - should fail
    $response2 = $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => $uid2,
        'name' => 'Device 2',
    ]);

    $response2->assertStatus(400)
        ->assertJson([
            'message' => 'Code is incorrect.',
        ]);

    // Verify only first device was created
    expect(Device::where('uid', $uid1)->exists())->toBeTrue()
        ->and(Device::where('uid', $uid2)->exists())->toBeFalse();
});

it('removes connect code from cache after use', function () {
    $user = User::factory()->create();
    $code = $user->getConnectCode();

    // Verify code exists in cache
    expect(Cache::has("connect-code:$code"))->toBeTrue();

    // Use the code
    $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => 'test-device-uid',
        'name' => 'Test Device',
    ])->assertOk();

    // Verify code is removed from cache
    expect(Cache::has("connect-code:$code"))->toBeFalse();
    expect(Cache::has("user:{$user->id}:connect-code"))->toBeFalse();
});

it('handles expired connect codes', function () {
    $user = User::factory()->create();
    $code = $user->getConnectCode();

    // Manually remove the code from cache to simulate expiration
    Cache::forget("connect-code:$code");
    Cache::forget("user:{$user->id}:connect-code");

    $response = $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => 'test-device-uid',
        'name' => 'Test Device',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Code is incorrect.',
        ]);
});

it('creates new device if device with same uid does not exist', function () {
    $user = User::factory()->create();
    $code = $user->getConnectCode();
    $uid = 'test-device-uid';

    $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => $uid,
        'name' => 'New Device',
    ])->assertOk();

    $device = Device::where('uid', $uid)->first();
    expect($device)->not->toBeNull()
        ->and($device->name)->toBe('New Device');
});

it('updates existing device when connecting with same uid', function () {
    $user = User::factory()->create();
    $existingDevice = Device::factory()->create([
        'user_id' => $user->id,
        'uid' => 'test-device-uid',
        'name' => 'Old Device Name',
        'workspace_id' => null,
    ]);

    $code = $user->getConnectCode();

    $response = $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => 'test-device-uid',
        'name' => 'Updated Device Name',
    ])->assertOk();

    // Verify device was updated, not duplicated
    $devices = Device::where('uid', 'test-device-uid')->get();
    expect($devices)->toHaveCount(1);

    $existingDevice->refresh();
    expect($existingDevice->name)->toBe('Updated Device Name')
        ->and($existingDevice->workspace_id)->toBe($user->primaryWorkspace()?->id);
});

it('works with different users having different codes', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    $code1 = $user1->getConnectCode();
    $code2 = $user2->getConnectCode();

    // Verify codes are different
    expect($code1)->not->toBe($code2);

    // Connect device 1 with user 1's code
    $response1 = $this->postJson('/api/auth/login', [
        'code' => $code1,
        'uid' => 'device-1',
        'name' => 'Device 1',
    ])->assertOk();

    // Connect device 2 with user 2's code
    $response2 = $this->postJson('/api/auth/login', [
        'code' => $code2,
        'uid' => 'device-2',
        'name' => 'Device 2',
    ])->assertOk();

    // Verify devices are connected to correct users
    $device1 = Device::where('uid', 'device-1')->first();
    $device2 = Device::where('uid', 'device-2')->first();

    expect($device1->user_id)->toBe($user1->id)
        ->and($device2->user_id)->toBe($user2->id);
});

it('returns same code when getConnectCode is called multiple times before expiration', function () {
    $user = User::factory()->create();
    
    $code1 = $user->getConnectCode();
    $code2 = $user->getConnectCode();

    expect($code1)->toBe($code2);
});

it('generates new code after previous code is used', function () {
    $user = User::factory()->create();
    $code1 = $user->getConnectCode();

    // Use the code
    $this->postJson('/api/auth/login', [
        'code' => $code1,
        'uid' => 'test-device-uid',
        'name' => 'Test Device',
    ])->assertOk();

    // Get a new code - should be different
    $code2 = $user->getConnectCode();
    expect($code2)->not->toBe($code1);
});

it('validates required fields', function () {
    // Missing code
    $this->postJson('/api/auth/login', [
        'uid' => 'test-device-uid',
        'name' => 'Test Device',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    // Missing uid
    $this->postJson('/api/auth/login', [
        'code' => '123456',
        'name' => 'Test Device',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['uid']);

    // Missing name
    $this->postJson('/api/auth/login', [
        'code' => '123456',
        'uid' => 'test-device-uid',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('handles case when user associated with code no longer exists', function () {
    $user = User::factory()->create();
    $code = $user->getConnectCode();
    
    // Manually set cache with a non-existent user ID
    Cache::put("connect-code:$code", 'non-existent-user-id', now()->addMinutes(30));

    $response = $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => 'test-device-uid',
        'name' => 'Test Device',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Code is incorrect.',
        ]);
});

it('returns token that can be used for authenticated requests', function () {
    $user = User::factory()->create();
    $code = $user->getConnectCode();

    $response = $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => 'test-device-uid',
        'name' => 'Test Device',
    ])->assertOk();

    $token = $response->json('data.token');
    expect($token)->not->toBeNull();

    // Verify token can be used for authenticated requests
    $device = Device::where('uid', 'test-device-uid')->first();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/devices/me')
        ->assertOk();
});
