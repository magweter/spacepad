<?php

use App\Enums\OutlookBookingMethod;
use App\Models\OutlookAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function consentNonceFor(OutlookAccount $account): string
{
    $response = test()->actingAs(User::find($account->user_id))
        ->post(route('outlook-accounts.set-booking-method'), [
            'outlook_account_id' => $account->id,
            'booking_method' => OutlookBookingMethod::ADMIN_CONSENT->value,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('admin_consent_url');

    parse_str(parse_url(session('admin_consent_url'), PHP_URL_QUERY), $query);

    expect($query['state'] ?? '')->toBeString()->not->toBeEmpty();

    return $query['state'];
}

function consentAccount(): OutlookAccount
{
    $user = User::factory()->active()->create();

    return OutlookAccount::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->primaryWorkspace()->id,
        'booking_method' => OutlookBookingMethod::USER_ACCOUNT,
    ]);
}

/**
 * The admin-consent callback has to work without a Spacepad session, because the Microsoft
 * 365 admin who approves the app is usually not the person who set it up. That made the
 * account id in the state parameter the only thing the callback trusted, so anyone who knew
 * an account's ULID could flip its booking method with a single GET.
 */
test('a callback that only names the account id no longer changes anything', function () {
    $account = consentAccount();

    $this->get('/outlook-accounts/callback?admin_consent=True&state=account:'.$account->id.'&tenant='.$account->tenant_id)
        ->assertStatus(403);

    expect($account->fresh()->booking_method)->toBe(OutlookBookingMethod::USER_ACCOUNT);
});

test('a callback with a made-up nonce changes nothing', function () {
    $account = consentAccount();

    $this->get('/outlook-accounts/callback?admin_consent=True&state=not-a-real-nonce&tenant='.$account->tenant_id)
        ->assertStatus(403);

    expect($account->fresh()->booking_method)->toBe(OutlookBookingMethod::USER_ACCOUNT);
});

test('a callback with the nonce issued to the account owner saves the booking method', function () {
    $account = consentAccount();
    $nonce = consentNonceFor($account);

    $this->get('/outlook-accounts/callback?admin_consent=True&state='.$nonce.'&tenant='.$account->tenant_id)
        ->assertOk()
        ->assertViewIs('outlook.admin-consent-granted');

    expect($account->fresh()->booking_method)->toBe(OutlookBookingMethod::ADMIN_CONSENT);
});

test('the nonce is good for one callback', function () {
    $account = consentAccount();
    $nonce = consentNonceFor($account);

    $this->get('/outlook-accounts/callback?admin_consent=True&state='.$nonce.'&tenant='.$account->tenant_id)
        ->assertOk();

    $this->get('/outlook-accounts/callback?admin_consent=True&state='.$nonce.'&tenant='.$account->tenant_id)
        ->assertStatus(403);
});

test('consent granted in a different tenant than the account belongs to is refused', function () {
    $account = consentAccount();
    $nonce = consentNonceFor($account);

    $this->get('/outlook-accounts/callback?admin_consent=True&state='.$nonce.'&tenant=00000000-0000-0000-0000-000000000000')
        ->assertStatus(403);

    expect($account->fresh()->booking_method)->toBe(OutlookBookingMethod::USER_ACCOUNT);
});
