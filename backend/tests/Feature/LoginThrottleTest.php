<?php

use App\Models\User;
use App\Notifications\MagicLoginNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * POST /login sends a magic link and creates an unverified user for any address it has not
 * seen before. Without a rate limit a single client could use it to mass-mail arbitrary
 * addresses from our domain and fill the users table with junk rows.
 */
test('magic link login is rate limited per ip', function () {
    Notification::fake();

    for ($i = 1; $i <= 5; $i++) {
        $this->post(route('login.store'), ['email' => "person{$i}@example.com"])
            ->assertRedirect();
    }

    $this->post(route('login.store'), ['email' => 'person6@example.com'])
        ->assertStatus(429);

    expect(User::count())->toBe(5);
    Notification::assertCount(5);
});

test('magic link login still works within the limit', function () {
    Notification::fake();

    $user = User::factory()->active()->create();

    $this->post(route('login.store'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentTo($user, MagicLoginNotification::class);
});
