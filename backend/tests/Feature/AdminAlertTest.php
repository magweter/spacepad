<?php

use App\Models\PortalAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The admin "Alerts" tab: create, end and list alerts, and the portal sees each change at once.
 */
beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->admin = User::factory()->active()->create(['is_admin' => true]);
    $this->workspace = User::factory()->active()->create()->primaryWorkspace();
});

test('an admin creates an alert and it clears the cache', function () {
    expect(PortalAlert::forWorkspace($this->workspace))->toBe([]);

    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), ['title' => 'Microsoft 365 outage', 'tone' => 'attention'])
        ->assertRedirect(route('admin.alerts.index'));

    expect(PortalAlert::forWorkspace($this->workspace))->toHaveCount(1)
        ->and(PortalAlert::first()->created_by)->toBe($this->admin->id);
});

test('ending an alert removes it at once', function () {
    $alert = PortalAlert::factory()->create();
    expect(PortalAlert::forWorkspace($this->workspace))->toHaveCount(1);

    $this->actingAs($this->admin)
        ->post(route('admin.alerts.end', $alert))
        ->assertRedirect(route('admin.alerts.index'));

    expect($alert->fresh()->ends_at)->not->toBeNull()
        ->and($alert->fresh()->ends_at->lte(now()))->toBeTrue()
        ->and(PortalAlert::forWorkspace($this->workspace))->toBe([]);
});

test('editing an alert shows the new text at once', function () {
    $alert = PortalAlert::factory()->create(['title' => 'Old title']);
    PortalAlert::forWorkspace($this->workspace);

    $this->actingAs($this->admin)
        ->put(route('admin.alerts.update', $alert), ['title' => 'New title', 'tone' => 'action_required'])
        ->assertRedirect(route('admin.alerts.index'));

    expect(PortalAlert::forWorkspace($this->workspace)[0])->toMatchArray(['title' => 'New title', 'tone' => 'action_required']);
});

test('an end before the start is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), [
            'title' => 'Outage', 'tone' => 'attention',
            'starts_at' => '2026-12-01 10:00', 'ends_at' => '2026-12-01 09:00',
        ])
        ->assertSessionHasErrors('ends_at');

    expect(PortalAlert::count())->toBe(0);
});

test('times sent with an offset are stored as that moment and listed as ISO', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), [
            'title' => 'Planned maintenance', 'tone' => 'attention',
            'starts_at' => '2030-12-01T10:00:00+01:00', 'ends_at' => '2030-12-01T12:00:00+01:00',
        ])
        ->assertSessionHasNoErrors();

    $alert = PortalAlert::first();
    expect($alert->starts_at->utc()->format('Y-m-d H:i'))->toBe('2030-12-01 09:00')
        ->and($alert->ends_at->utc()->format('Y-m-d H:i'))->toBe('2030-12-01 11:00');

    $this->actingAs($this->admin)->get(route('admin.alerts.index'))
        ->assertInertia(fn (Assert $page) => $page->where('adminAlerts.scheduled.0.startsAt', '2030-12-01T09:00:00+00:00'));
});

test('an ended alert can be saved again', function () {
    $ended = PortalAlert::factory()->create(['starts_at' => null, 'ends_at' => now()->subHour()]);
    $cancelled = PortalAlert::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()]);

    foreach ([$ended, $cancelled] as $alert) {
        $this->actingAs($this->admin)
            ->put(route('admin.alerts.update', $alert), [
                'title' => 'Fixed typo', 'tone' => 'attention',
                'starts_at' => $alert->starts_at?->toIso8601String(), 'ends_at' => $alert->ends_at->toIso8601String(),
            ])
            ->assertSessionHasNoErrors();
    }

    expect(PortalAlert::where('title', 'Fixed typo')->count())->toBe(2);
});

test('a new alert that ends in the past says so', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), ['title' => 'Outage', 'tone' => 'attention', 'ends_at' => now()->subHour()->toIso8601String()])
        ->assertSessionHasErrors(['ends_at' => 'The end has to be in the future.']);
});

test('an unknown tone or provider is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), ['title' => 'Outage', 'tone' => 'loud', 'provider' => 'yahoo'])
        ->assertSessionHasErrors(['tone', 'provider']);
});

test('the list groups alerts by status', function () {
    PortalAlert::factory()->create();
    PortalAlert::factory()->scheduled()->create();
    PortalAlert::factory()->ended()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.alerts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Alerts')
            ->has('adminAlerts.active', 1)
            ->has('adminAlerts.scheduled', 1)
            ->has('adminAlerts.ended', 1));
});

test('the alerts page keeps the shared running alerts a list the portal shell can read', function () {
    PortalAlert::factory()->create(['title' => 'Outage']);

    // The page's own alerts are grouped by status under another name; reusing 'alerts' broke the
    // popup in the shell, which reads the shared running alerts on every page.
    $this->actingAs($this->admin)
        ->get(route('admin.alerts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts', fn ($alerts) => array_is_list(collect($alerts)->all()))
            ->has('adminAlerts.active', 1));
});

test('an alert can carry an image, which can be replaced and removed', function () {
    Storage::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), ['title' => 'New dashboard', 'tone' => 'new', 'popup' => true, 'image' => UploadedFile::fake()->image('screen.png', 800, 400)])
        ->assertRedirect(route('admin.alerts.index'));

    $alert = PortalAlert::where('title', 'New dashboard')->firstOrFail();
    expect($alert->image_path)->not->toBeNull();
    Storage::assertExists($alert->image_path);
    $first = $alert->image_path;

    // A new upload replaces the old file.
    $this->actingAs($this->admin)
        ->put(route('admin.alerts.update', $alert), ['title' => 'New dashboard', 'tone' => 'new', 'image' => UploadedFile::fake()->image('other.png')]);
    Storage::assertMissing($first);
    Storage::assertExists($alert->fresh()->image_path);

    $this->actingAs($this->admin)
        ->put(route('admin.alerts.update', $alert), ['title' => 'New dashboard', 'tone' => 'new', 'remove_image' => true]);
    expect($alert->fresh()->image_path)->toBeNull();
});

test('only an image is accepted', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), ['title' => 'Outage', 'tone' => 'attention', 'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('image');
});

test('the create and edit forms render', function () {
    $alert = PortalAlert::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.alerts.create'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/AlertForm')->where('alert', null));
    $this->actingAs($this->admin)->get(route('admin.alerts.edit', $alert))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/AlertForm')->where('alert.id', $alert->id));
});

test('a non-admin gets 403', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->get(route('admin.alerts.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.alerts.store'), ['title' => 'x', 'tone' => 'attention'])->assertForbidden();
    expect(PortalAlert::count())->toBe(0);
});

test('every tone from the list can be saved', function () {
    foreach (['info', 'new', 'maintenance', 'attention', 'service_degradation', 'action_required', 'resolved'] as $tone) {
        $this->actingAs($this->admin)
            ->post(route('admin.alerts.store'), ['title' => "A {$tone} alert", 'tone' => $tone])
            ->assertSessionHasNoErrors();
    }

    expect(PortalAlert::count())->toBe(7);
});

test('an alert can be saved as a popup', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.alerts.store'), ['title' => 'Price change', 'tone' => 'info', 'popup' => true])
        ->assertSessionHasNoErrors();
    $alert = PortalAlert::first();
    expect($alert->popup)->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('admin.alerts.update', $alert), ['title' => 'Price change', 'tone' => 'info'])
        ->assertSessionHasNoErrors();
    expect($alert->fresh()->popup)->toBeFalse();
});
