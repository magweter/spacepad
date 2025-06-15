<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('it redirects to login page when visiting dashboard unauthenticated', function () {
    $response = $this->get(route('dashboard'));

    $response->assertStatus(302);
    $response->assertRedirect(route('login'));
});

test('it redirects to login page when visiting displays unauthenticated', function () {
    $response = $this->get(route('displays.create'));

    $response->assertStatus(302);
    $response->assertRedirect(route('login'));
});

test('it loads login page successfully', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
    $response->assertViewIs('auth.login');
});

test('it loads register page successfully', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
    $response->assertViewIs('auth.register');
});

test('it loads dashboard when authenticated', function () {
    $user = User::factory()->active()->create();

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertViewIs('pages.dashboard');
});

test('it loads displays page when authenticated', function () {
    $user = User::factory()->active()->create();

    $response = $this->actingAs($user)
        ->get(route('displays.create'));

    $response->assertStatus(200);
    $response->assertViewIs('pages.displays.create');
});

test('it redirects to dashboard when visiting login while authenticated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('login'));

    $response->assertStatus(302);
    $response->assertRedirect(route('dashboard'));
});

test('it redirects to dashboard when visiting register while authenticated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('register'));

    $response->assertStatus(302);
    $response->assertRedirect(route('dashboard'));
});

test('it handles 404 for invalid routes', function () {
    $response = $this->get('/invalid-route');

    $response->assertStatus(404);
});

test('it handles 404 for invalid routes when authenticated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/invalid-route');

    $response->assertStatus(404);
});
