<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the account page shows who you are and the workspaces you are in', function () {
    $user = User::factory()->active()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $workspace = $user->primaryWorkspace();

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Account/Index')
            ->where('shell.accountActive', true)
            ->where('account.name', 'Ada Lovelace')
            ->where('account.email', 'ada@example.com')
            ->has('workspaces', 1)
            ->where('workspaces.0.name', $workspace->name)
            ->where('workspaces.0.role', 'Owner')
            ->where('urls.destroy', route('profile.destroy')));
});

test('a wrong confirmation keeps the account and returns to the page', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)
        ->from(route('profile.show'))
        ->delete(route('profile.destroy'), ['confirm_email' => 'someone@else.com'])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHasErrors('confirm_email');

    expect(User::find($user->id))->not->toBeNull();
});

test('the account can still be deleted', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirm_email' => $user->email])
        ->assertRedirect(route('login'));

    expect(User::find($user->id))->toBeNull();
});
