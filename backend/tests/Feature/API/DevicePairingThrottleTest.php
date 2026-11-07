<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * The connect code is six digits and lives for thirty minutes. Without a limit a client can
 * walk the whole code space well within that window and pair a device into someone else's
 * workspace.
 */
it('rate limits connect code attempts per ip', function () {
    for ($i = 1; $i <= 10; $i++) {
        $this->postJson('/api/auth/login', [
            'code' => sprintf('%06d', $i),
            'uid' => 'brute-force-device',
            'name' => 'Brute Force',
        ])->assertStatus(400);
    }

    $this->postJson('/api/auth/login', [
        'code' => '000011',
        'uid' => 'brute-force-device',
        'name' => 'Brute Force',
    ])->assertStatus(429);
});

it('still pairs a device with a valid code within the limit', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/login', [
        'code' => $user->getConnectCode(),
        'uid' => 'tablet-1',
        'name' => 'Tablet',
    ])->assertOk();
});
