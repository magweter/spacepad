<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function socialiteUser(string $id, string $email, array $raw = []): SocialiteUser
{
    return (new SocialiteUser)
        ->setRaw(array_merge(['id' => $id, 'email' => $email, 'name' => 'Social Person'], $raw))
        ->map(['id' => $id, 'email' => $email, 'name' => 'Social Person', 'nickname' => null, 'avatar' => null]);
}

/**
 * The provider returns the user straight from the callback. The driver must be the
 * stateful one so Socialite checks the OAuth state against the session: with
 * stateless() a code captured from a flow the attacker started could be planted in a
 * victim's browser, logging the victim in as the attacker (login CSRF).
 */
function fakeSocialCallback(string $driver, SocialiteUser $user): void
{
    Socialite::shouldReceive('driver')->with($driver)->andReturn(
        Mockery::mock(AbstractProvider::class, function (MockInterface $provider) use ($user) {
            $provider->shouldNotReceive('stateless');
            $provider->shouldReceive('user')->once()->andReturn($user);
        })
    );
}

test('the social redirect goes through the stateful driver', function () {
    Socialite::shouldReceive('driver')->with('google')->andReturn(
        Mockery::mock(AbstractProvider::class, function (MockInterface $provider) {
            $provider->shouldNotReceive('stateless');
            $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth?state=x'));
        })
    );

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth?state=x');
});

test('the social callback reads the user through the stateful driver', function () {
    fakeSocialCallback('google', socialiteUser('g-1', 'new@example.com', ['email_verified' => true]));

    $this->get('/auth/google/callback?code=abc&state=x')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(User::where('email', 'new@example.com')->value('google_id'))->toBe('g-1');
});

/**
 * Linking a provider identity to an existing account on nothing but an e-mail match is
 * an account takeover when the provider has not verified that address. Google does
 * return unverified addresses for some non-Gmail accounts.
 */
test('a google identity with an unverified e-mail is not linked to an existing account', function () {
    $victim = User::factory()->active()->create(['email' => 'victim@example.com']);

    fakeSocialCallback('google', socialiteUser('g-attacker', 'victim@example.com', ['email_verified' => false]));

    $this->get('/auth/google/callback?code=abc&state=x')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect($victim->fresh()->google_id)->toBeNull();
});

test('a google identity with an unverified e-mail does not create an account either', function () {
    fakeSocialCallback('google', socialiteUser('g-2', 'someone@example.com', ['email_verified' => false]));

    $this->get('/auth/google/callback?code=abc&state=x')
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::where('email', 'someone@example.com')->exists())->toBeFalse();
});

test('a google identity with a verified e-mail is linked to the existing account', function () {
    $user = User::factory()->active()->create(['email' => 'me@example.com']);

    fakeSocialCallback('google', socialiteUser('g-3', 'me@example.com', ['email_verified' => true]));

    $this->get('/auth/google/callback?code=abc&state=x')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('g-3');
});

test('an identity already linked by provider id logs in regardless of the e-mail flag', function () {
    $user = User::factory()->active()->create(['email' => 'me@example.com', 'google_id' => 'g-4']);

    fakeSocialCallback('google', socialiteUser('g-4', 'me@example.com', ['email_verified' => false]));

    $this->get('/auth/google/callback?code=abc&state=x')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('a microsoft identity links by e-mail, the principal name is tenant verified', function () {
    $user = User::factory()->active()->create(['email' => 'me@example.com']);

    fakeSocialCallback('microsoft', socialiteUser('ms-1', 'me@example.com', ['userPrincipalName' => 'me@example.com']));

    $this->get('/auth/microsoft/callback?code=abc&state=x')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->microsoft_id)->toBe('ms-1');
});
