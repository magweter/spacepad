<?php

use App\Enums\PermissionType;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Services\GoogleService;
use App\Services\OutlookService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Linking a calendar account is an OAuth authorization-code flow. Without a session-bound
 * state parameter an attacker can start the flow with their own provider account, keep the
 * resulting code, and have a logged-in victim open the callback URL: the attacker's calendar
 * then lands in the victim's workspace and every booking made from a tablet ends up there.
 */
describe('google account linking', function () {
    test('the authorize redirect carries a state that is remembered in the session', function () {
        $user = User::factory()->active()->create();

        $this->mock(GoogleService::class, function ($mock) {
            $mock->shouldReceive('getAuthUrl')
                ->once()
                ->withArgs(fn (PermissionType $type, string $state) => strlen($state) >= 32)
                ->andReturnUsing(fn ($type, $state) => 'https://accounts.google.com/o/oauth2/auth?state='.$state);
        });

        $response = $this->actingAs($user)
            ->post(route('google-accounts.auth'), ['permission_type' => PermissionType::READ->value]);

        $state = session('google_oauth_state');
        expect($state)->toBeString()->not->toBeEmpty();
        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth?state='.$state);
    });

    test('the callback refuses a code when the state does not match the session', function () {
        $user = User::factory()->active()->create();

        $this->mock(GoogleService::class, function ($mock) {
            $mock->shouldNotReceive('authenticateGoogleAccount');
        });

        $this->actingAs($user)
            ->withSession(['google_oauth_state' => 'expected-state'])
            ->get('/google-accounts/callback?code=attacker-code&state=other-state')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    });

    test('the callback refuses a code when no state was issued in this session', function () {
        $user = User::factory()->active()->create();

        $this->mock(GoogleService::class, function ($mock) {
            $mock->shouldNotReceive('authenticateGoogleAccount');
        });

        $this->actingAs($user)
            ->get('/google-accounts/callback?code=attacker-code&state=anything')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    });

    test('the callback links the account when the state matches, and the state is single use', function () {
        $user = User::factory()->active()->create();
        $account = GoogleAccount::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->primaryWorkspace()->id,
            'permission_type' => PermissionType::READ,
        ]);

        $this->mock(GoogleService::class, function ($mock) use ($account) {
            $mock->shouldReceive('authenticateGoogleAccount')->once()->andReturn($account);
        });

        $this->actingAs($user)
            ->withSession(['google_oauth_state' => 'expected-state'])
            ->get('/google-accounts/callback?code=real-code&state=expected-state')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success')
            ->assertSessionMissing('google_oauth_state');
    });
});

describe('outlook account linking', function () {
    test('the authorize redirect carries a state that is remembered in the session', function () {
        $user = User::factory()->active()->create();

        $this->mock(OutlookService::class, function ($mock) {
            $mock->shouldReceive('getAuthUrl')
                ->once()
                ->withArgs(fn (PermissionType $type, string $state) => strlen($state) >= 32)
                ->andReturnUsing(fn ($type, $state) => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?state='.$state);
        });

        $response = $this->actingAs($user)
            ->post(route('outlook-accounts.auth'), ['permission_type' => PermissionType::READ->value]);

        $state = session('outlook_oauth_state');
        expect($state)->toBeString()->not->toBeEmpty();
        $response->assertRedirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?state='.$state);
    });

    test('the callback refuses a code when the state does not match the session', function () {
        $user = User::factory()->active()->create();

        $this->mock(OutlookService::class, function ($mock) {
            $mock->shouldNotReceive('authenticateOutlookAccount');
        });

        $this->actingAs($user)
            ->withSession(['outlook_oauth_state' => 'expected-state'])
            ->get('/outlook-accounts/callback?code=attacker-code&state=other-state')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    });

    test('the callback refuses a code when no state was issued in this session', function () {
        $user = User::factory()->active()->create();

        $this->mock(OutlookService::class, function ($mock) {
            $mock->shouldNotReceive('authenticateOutlookAccount');
        });

        $this->actingAs($user)
            ->get('/outlook-accounts/callback?code=attacker-code&state=anything')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    });

    test('the callback links the account when the state matches, and the state is single use', function () {
        $user = User::factory()->active()->create();
        $account = OutlookAccount::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->primaryWorkspace()->id,
        ]);

        $this->mock(OutlookService::class, function ($mock) use ($account) {
            $mock->shouldReceive('authenticateOutlookAccount')->once()->andReturn($account);
        });

        $this->actingAs($user)
            ->withSession(['outlook_oauth_state' => 'expected-state'])
            ->get('/outlook-accounts/callback?code=real-code&state=expected-state')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success')
            ->assertSessionMissing('outlook_oauth_state');
    });
});
