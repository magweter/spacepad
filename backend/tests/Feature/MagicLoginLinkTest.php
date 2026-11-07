<?php

use App\Models\User;
use App\Notifications\MagicLoginNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Request a login link for the user and return the URL that would have been mailed.
 */
function requestMagicLink(User $user): string
{
    Notification::fake();

    test()->post(route('login.store'), ['email' => $user->email])->assertRedirect();

    $url = null;
    Notification::assertSentTo($user, MagicLoginNotification::class, function (MagicLoginNotification $notification) use (&$url) {
        $url = $notification->loginUrl;

        return true;
    });

    return $url;
}

/**
 * A login link is a password that travels by e-mail. It used to be good for a day and for
 * any number of visits, so a link left in a shared mailbox, logged by a proxy or picked up
 * from a mail scanner kept logging people in long after the person who asked for it was done.
 */
test('a login link works once', function () {
    $user = User::factory()->active()->create();
    $url = requestMagicLink($user);

    $this->get($url)->assertRedirect('/');
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'));
    $this->assertGuest();

    $this->get($url)->assertForbidden();
    $this->assertGuest();
});

test('a login link expires after fifteen minutes', function () {
    $user = User::factory()->active()->create();
    $url = requestMagicLink($user);

    $this->travel(16)->minutes();

    $this->get($url)->assertForbidden();
    $this->assertGuest();
});

test('the e-mail says how long the link lasts', function () {
    $mail = (new MagicLoginNotification('https://example.com/magiclink/abc'))
        ->toMail(User::factory()->make());

    $text = implode(' ', array_map('strval', array_merge($mail->introLines, $mail->outroLines)));

    expect($text)->toContain('15 minutes')->not->toContain('24 hours');
});

test('guessing login links is rate limited per ip', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->get('/magiclink/'.str_repeat('x', 64))->assertForbidden();
    }

    $this->get('/magiclink/'.str_repeat('y', 64))->assertStatus(429);
});
