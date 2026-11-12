<?php

use App\Enums\DisplayStatus;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Models\User;
use App\Services\GoogleService;
use App\Services\RoomDetailsService;
use Google\Service\Directory\CalendarResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->unlimited()->create(); // Pro
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);

    $this->account = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);
});

function graphRoom(string $email, array $extra = []): array
{
    return ['emailAddress' => $email, 'displayName' => 'Boardroom', ...$extra];
}

function displayOnRoom(OutlookAccount $account, string $email, array $attributes = []): Display
{
    $calendar = Calendar::create([
        'calendar_id' => $email,
        'user_id' => $account->user_id,
        'workspace_id' => $account->workspace_id,
        'outlook_account_id' => $account->id,
        'name' => 'Boardroom',
    ]);
    Room::create([
        'email_address' => $email,
        'user_id' => $account->user_id,
        'workspace_id' => $account->workspace_id,
        'calendar_id' => $calendar->id,
        'name' => 'Boardroom',
    ]);

    return Display::factory()->create([
        'workspace_id' => $account->workspace_id,
        'user_id' => $account->user_id,
        'calendar_id' => $calendar->id,
        'status' => DisplayStatus::ACTIVE,
        ...$attributes,
    ]);
}

test('a Microsoft room gives its floor label and capacity', function () {
    expect(RoomDetailsService::fromGraphRoom(['floorLabel' => '2', 'capacity' => 12]))
        ->toBe(['location' => 'Floor 2', 'capacity' => 12])
        ->and(RoomDetailsService::fromGraphRoom(['floorLabel' => 'Ground floor', 'capacity' => 4]))
        ->toBe(['location' => 'Ground floor', 'capacity' => 4])
        ->and(RoomDetailsService::fromGraphRoom(['floorLabel' => null, 'floorNumber' => 3]))
        ->toBe(['location' => 'Floor 3', 'capacity' => null])
        ->and(RoomDetailsService::fromGraphRoom(['capacity' => 0]))
        ->toBe(['location' => null, 'capacity' => null]);
});

test('a Google resource gives its floor, section and capacity', function () {
    $resource = new CalendarResource(['floorName' => '1', 'floorSection' => 'East wing', 'capacity' => 8]);

    expect(RoomDetailsService::fromGoogleResource($resource))
        ->toBe(['location' => 'Floor 1, East wing', 'capacity' => 8]);
});

test('the configure page fills in the floor and seats from Microsoft 365', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(['value' => [
        graphRoom('other@example.com', ['floorLabel' => '9', 'capacity' => 2]),
        graphRoom('Boardroom@Example.com', ['floorLabel' => '1', 'capacity' => 12]),
    ]])]);

    $display = displayOnRoom($this->account, 'boardroom@example.com');

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertOk()
        ->assertSee('value="Floor 1"', false)
        ->assertSee('value="12"', false)
        ->assertSee('Filled in from your calendar. Save to keep it.');

    // Only offered: nothing is stored until Save.
    expect($display->fresh()->location)->toBeNull();
});

test('the room list is asked for once an hour, not on every visit', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(['value' => [
        graphRoom('boardroom@example.com', ['floorLabel' => '1', 'capacity' => 12]),
    ]])]);

    $display = displayOnRoom($this->account, 'boardroom@example.com');

    $this->actingAs($this->user)->get(route('displays.configure', $display))->assertOk();
    $this->actingAs($this->user)->get(route('displays.configure', $display))->assertOk();

    Http::assertSentCount(1);
});

test('a value someone typed is never replaced', function () {
    app(RoomDetailsService::class)->remember($this->account, [
        graphRoom('boardroom@example.com', ['floorLabel' => '1', 'capacity' => 12]),
    ]);

    $display = displayOnRoom($this->account, 'boardroom@example.com', ['location' => 'Wing B']);

    expect(app(RoomDetailsService::class)->fillMissing($display))->toBeTrue();

    $fresh = $display->fresh();
    expect($fresh->location)->toBe('Wing B')
        ->and($fresh->capacity)->toBe(12);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertSee('value="Wing B"', false)
        ->assertDontSee('Filled in from your calendar');
});

test('a new display on a room gets the floor and seats from the room picker list', function () {
    // The room picker fetched the list; creating the display reads it from there.
    Http::fake(['graph.microsoft.com/*' => Http::response(['value' => [
        graphRoom('boardroom@example.com', ['floorLabel' => '3', 'capacity' => 6]),
    ]])]);

    $this->actingAs($this->user)->get(route('rooms.outlook', $this->account->id))->assertOk();

    $this->actingAs($this->user)
        ->post(route('displays.store'), [
            'name' => 'Boardroom tablet',
            'displayName' => 'Boardroom',
            'provider' => 'outlook',
            'account' => $this->account->id,
            'room' => 'boardroom@example.com,Boardroom',
        ])
        ->assertRedirect(route('dashboard'));

    $display = Display::where('name', 'Boardroom tablet')->firstOrFail();
    expect($display->location)->toBe('Floor 3')
        ->and($display->capacity)->toBe(6);

    // The places list once, for the picker. Creating the display sends other calls of its own.
    expect(Http::recorded(fn ($request) => str_contains($request->url(), '/places/'))->count())->toBe(1);
});

test('creating a display never waits on the provider', function () {
    Http::fake();

    $this->actingAs($this->user)
        ->post(route('displays.store'), [
            'name' => 'Boardroom tablet',
            'displayName' => 'Boardroom',
            'provider' => 'outlook',
            'account' => $this->account->id,
            'room' => 'boardroom@example.com,Boardroom',
        ])
        ->assertRedirect(route('dashboard'));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/places/'));
    expect(Display::where('name', 'Boardroom tablet')->firstOrFail()->location)->toBeNull();
});

test('a display on a personal calendar has no room details and asks nobody', function () {
    $google = GoogleAccount::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);
    $this->mock(GoogleService::class)->shouldNotReceive('fetchRooms');

    $calendar = Calendar::create([
        'calendar_id' => 'someone@example.com',
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'google_account_id' => $google->id,
        'name' => 'Personal',
    ]);
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'calendar_id' => $calendar->id,
    ]);

    expect(app(RoomDetailsService::class)->forDisplay($display))->toBeNull();
});

test('a provider that cannot be reached leaves the fields empty', function () {
    Http::fake(['graph.microsoft.com/*' => fn () => throw new ConnectionException('down')]);

    $display = displayOnRoom($this->account, 'boardroom@example.com');

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertOk()
        ->assertDontSee('Filled in from your calendar');
});
