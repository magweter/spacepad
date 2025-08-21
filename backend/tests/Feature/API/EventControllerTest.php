<?php

use App\Models\Device;
use App\Models\Display;
use App\Models\EventSubscription;
use App\Models\User;
use App\Services\OutlookService;
use App\Services\GoogleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Calendar;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Room;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = Device::factory()->create(['user_id' => $this->user->id]);

    // Create calendar first
    $this->calendar = Calendar::factory()->create([
        'user_id' => $this->user->id,
        'calendar_id' => 'test@example.com',
        'name' => 'Test Calendar'
    ]);

    // Then create display with calendar
    $this->display = Display::factory()->create([
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'status' => 'active'
    ]);

    $this->device->update(['display_id' => $this->display->id]);
});

it('returns 400 when device is not connected to a display', function () {
    $this->device->update(['display_id' => null]);

    $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertStatus(404)
        ->assertJson(['message' => 'Display not found']);
});

it('returns 400 when display is deactivated', function () {
    $this->display->update(['status' => 'deactivated']);

    $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertStatus(400)
        ->assertJson(['message' => 'Display is deactivated']);
});

it('returns outlook events in the correct format', function () {
    // Create accounts and link them to the calendar
    $outlookAccount = OutlookAccount::factory()->create(['user_id' => $this->user->id]);
    Room::factory()->create([
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'email_address' => 'test@example.com'
    ]);

    $this->calendar->update([
        'outlook_account_id' => $outlookAccount->id
    ]);

    // Mock Outlook service response
    $outlookEvents = [
        [
            'id' => 'outlook-1',
            'subject' => 'Test Outlook Event',
            'body' => ['content' => 'Test Description'],
            'bodyPreview' => 'Test Description',
            'isAllDay' => false,
            'location' => ['displayName' => 'Test Location'],
            'start' => [
                'dateTime' => now()->addHour()->toIso8601String(),
                'timeZone' => 'UTC'
            ],
            'end' => [
                'dateTime' => now()->addHours(2)->toIso8601String(),
                'timeZone' => 'UTC'
            ]
        ]
    ];

    // Mock the service
    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')
        ->once()
        ->andReturn($outlookEvents);

    $this->app->instance(OutlookService::class, $outlookService);

    $response = $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'summary',
                    'location',
                    'description',
                    'start',
                    'end',
                    'timezone',
                ]
            ]
        ]);

    $events = $response->json('data');
    expect($events)->toHaveCount(1);

    // Verify Outlook event format
    $event = $events[0];
    expect($event)->toBeArray()
        ->and($event['summary'])->toBe('Test Outlook Event')
        ->and($event['location'])->toBe('Test Location')
        ->and($event['description'])->toBe('Test Description')
        ->and($event['timezone'])->toBe('UTC');
});

it('returns google events in the correct format', function () {
    // Create accounts and link them to the calendar
    $googleAccount = GoogleAccount::factory()->create(['user_id' => $this->user->id]);
    Room::factory()->create([
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'email_address' => 'test@example.com'
    ]);

    $this->calendar->update([
        'google_account_id' => $googleAccount->id
    ]);

    // Mock Google service response
    $googleEvent = new \Google\Service\Calendar\Event();
    $googleEvent->setId('google-1');
    $googleEvent->setSummary('Test Google Event');
    $googleEvent->setDescription('Test Description');
    $googleEvent->setLocation('Test Location');
    $googleEvent->setStart(new \Google\Service\Calendar\EventDateTime([
        'dateTime' => now()->addHour()->toIso8601String(),
        'timeZone' => 'UTC'
    ]));
    $googleEvent->setEnd(new \Google\Service\Calendar\EventDateTime([
        'dateTime' => now()->addHours(2)->toIso8601String(),
        'timeZone' => 'UTC'
    ]));

    // Mock the service
    $googleService = Mockery::mock(GoogleService::class);
    $googleService->shouldReceive('fetchEvents')
        ->once()
        ->with(
            Mockery::type(GoogleAccount::class),
            'test@example.com',
            Mockery::type(\Carbon\Carbon::class),
            Mockery::type(\Carbon\Carbon::class)
        )
        ->andReturn([$googleEvent]);

    $this->app->instance(GoogleService::class, $googleService);

    $response = $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'summary',
                    'location',
                    'description',
                    'start',
                    'end',
                    'timezone'
                ]
            ]
        ]);

    $events = $response->json('data');
    expect($events)->toHaveCount(1);

    // Verify Google event format
    $event = $events[0];
    expect($event)->toBeArray()
        ->and($event['summary'])->toBe('Test Google Event')
        ->and($event['location'])->toBe('Test Location')
        ->and($event['description'])->toBe('Test Description')
        ->and($event['timezone'])->toBe('UTC');
});

it('does not cache events when no event subscription exists', function () {
    // Create accounts and link them to the calendar
    $outlookAccount = OutlookAccount::factory()->create(['user_id' => $this->user->id]);
    $room = Room::factory()->create([
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'email_address' => 'test@example.com'
    ]);

    $this->calendar->update([
        'outlook_account_id' => $outlookAccount->id
    ]);

    // Mock Outlook service response
    $outlookEvents = [
        [
            'id' => 'outlook-1',
            'subject' => 'Test Outlook Event',
            'body' => ['content' => 'Test Description'],
            'bodyPreview' => 'Test Description',
            'isAllDay' => false,
            'location' => ['displayName' => 'Test Location'],
            'start' => [
                'dateTime' => now()->addHour()->toIso8601String(),
                'timeZone' => 'UTC'
            ],
            'end' => [
                'dateTime' => now()->addHours(2)->toIso8601String(),
                'timeZone' => 'UTC'
            ]
        ]
    ];

    // Mock the service
    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')
        ->twice() // Should be called twice since no caching
        ->andReturn($outlookEvents);

    $this->app->instance(OutlookService::class, $outlookService);

    // First request
    $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertOk();

    // Second request
    $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertOk();

    // Verify no cache exists
    expect(cache()->has($this->display->getEventsCacheKey()))->toBeFalse();
});

it('caches events when event subscription exists', function () {
    // Create accounts and link them to the calendar
    $outlookAccount = OutlookAccount::factory()->create(['user_id' => $this->user->id]);
    Room::factory()->create([
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'email_address' => 'test@example.com'
    ]);

    $this->calendar->update([
        'outlook_account_id' => $outlookAccount->id
    ]);

    // Create event subscription for the Outlook account
    $test = EventSubscription::factory()
        ->outlook($outlookAccount)
        ->create([
            'display_id' => $this->display->id
        ]);

    // Mock Outlook service response
    $outlookEvents = [
        [
            'id' => 'outlook-1',
            'subject' => 'Test Outlook Event',
            'body' => ['content' => 'Test Description'],
            'bodyPreview' => 'Test Description',
            'isAllDay' => false,
            'location' => ['displayName' => 'Test Location'],
            'start' => [
                'dateTime' => now()->addHour()->toIso8601String(),
                'timeZone' => 'UTC'
            ],
            'end' => [
                'dateTime' => now()->addHours(2)->toIso8601String(),
                'timeZone' => 'UTC'
            ]
        ]
    ];

    // Mock the service
    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')
        ->once() // Should be called only once due to caching
        ->andReturn($outlookEvents);

    $this->app->instance(OutlookService::class, $outlookService);

    // First request should call the service
    $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertOk();

    // Second request should use cache
    $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertOk();

    // Verify cache exists
    expect(cache()->has($this->display->getEventsCacheKey()))->toBeTrue();
});

it('handles errors gracefully', function () {
    // Create accounts and link them to the calendar
    $outlookAccount = OutlookAccount::factory()->create(['user_id' => $this->user->id]);
    $room = Room::factory()->create([
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'email_address' => 'test@example.com'
    ]);

    $this->calendar->update([
        'outlook_account_id' => $outlookAccount->id
    ]);

    // Mock the service to throw an exception
    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')
        ->once()
        ->andThrow(new \Exception('Service error'));

    $this->app->instance(OutlookService::class, $outlookService);

    $this->actingAs($this->device)
        ->getJson('/api/events')
        ->assertStatus(500)
        ->assertJson(['message' => 'Service error']);
});
