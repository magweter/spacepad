<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Enums\EventStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\Event;
use App\Models\User;
use App\Models\Workspace;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->unlimited()->create();
    $this->workspace = $this->user->primaryWorkspace();

    // Set selected workspace in session
    session()->put('selected_workspace_id', $this->workspace->id);
});

test('user can create a board', function () {
    Display::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('boards.store'), [
            'name' => 'Test Board',
            'workspace_id' => $this->workspace->id,
            'show_all_displays' => true,
            'theme' => 'dark',
            'show_title' => true,
            'show_booker' => true,
            'show_next_event' => true,
            'show_transitioning' => true,
            'transitioning_minutes' => 10,
            'font_family' => 'Inter',
            'language' => 'en',
            'view_mode' => 'card',
            'show_meeting_title' => true,
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('boards', [
        'name' => 'Test Board',
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('user can view boards list', function () {
    Board::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('dashboard').'?tab=boards');

    $response->assertStatus(200);
    $response->assertSee('Boards');
});

test('user can view a board', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('boards.show', $board));

    $response->assertStatus(200);
    $response->assertViewIs('pages.boards.show');
});

test('user can update a board', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('boards.update', $board), [
            'name' => 'Updated Board Name',
            'workspace_id' => $this->workspace->id,
            'show_all_displays' => false,
            'theme' => 'light',
            'show_title' => true,
            'show_booker' => true,
            'show_next_event' => true,
            'show_transitioning' => true,
            'transitioning_minutes' => 10,
            'font_family' => 'Inter',
            'language' => 'en',
            'view_mode' => 'card',
            'show_meeting_title' => true,
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
        'name' => 'Updated Board Name',
        'theme' => 'light',
    ]);
});

test('user can delete a board', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('boards.destroy', $board));

    $response->assertRedirect();
    $this->assertDatabaseMissing('boards', [
        'id' => $board->id,
    ]);
});

/**
 * Base payload for a board form submission, so the category tests only spell out what they test.
 */
function boardPayload(Workspace $workspace, array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Board',
        'workspace_id' => $workspace->id,
        'show_all_displays' => true,
        'theme' => 'dark',
        'show_title' => true,
        'show_booker' => true,
        'show_next_event' => true,
        'show_transitioning' => true,
        'transitioning_minutes' => 10,
        'font_family' => 'Inter',
        'language' => 'en',
        'view_mode' => 'card',
        'show_meeting_title' => true,
    ], $overrides);
}

test('board stores room categories in the submitted order', function () {
    $displays = Display::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('boards.store'), boardPayload($this->workspace, [
            'name' => 'Categorised Board',
            'categories' => [
                ['name' => 'Floor 2', 'display_ids' => [$displays[2]->id]],
                ['name' => 'Floor 1', 'display_ids' => [$displays[0]->id, $displays[1]->id]],
            ],
        ]));

    $response->assertRedirect();

    $board = Board::where('name', 'Categorised Board')->sole();

    // "Floor 2" first: the submitted order is the order shown on the board.
    expect($board->categories)->toBe([
        ['name' => 'Floor 2', 'display_ids' => [$displays[2]->id]],
        ['name' => 'Floor 1', 'display_ids' => [$displays[0]->id, $displays[1]->id]],
    ]);
});

test('board normalizes categories on update', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    [$first, $second, $third] = Display::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ])->all();

    // A display from another workspace must never end up in this board's categories.
    $otherWorkspace = User::factory()->active()->create()->primaryWorkspace();
    $foreign = Display::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('boards.update', $board), boardPayload($this->workspace, [
            'name' => $board->name,
            'categories' => [
                ['name' => '  Floor 1  ', 'display_ids' => [$first->id, $foreign->id, $first->id]],
                ['name' => 'floor 1', 'display_ids' => [$second->id]],
                ['name' => '', 'display_ids' => [$third->id]],
            ],
        ]));

    $response->assertRedirect();

    // Name trimmed, the case-insensitive duplicate merged, the foreign and repeated ids dropped,
    // and the nameless category discarded (so $third stays ungrouped).
    expect($board->fresh()->categories)->toBe([
        ['name' => 'Floor 1', 'display_ids' => [$first->id, $second->id]],
    ]);
});

test('board keeps a display in only one category', function () {
    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $this->actingAs($this->user)
        ->put(route('boards.update', $board), boardPayload($this->workspace, [
            'name' => $board->name,
            'categories' => [
                ['name' => 'Floor 1', 'display_ids' => [$display->id]],
                ['name' => 'Floor 2', 'display_ids' => [$display->id]],
            ],
        ]))
        ->assertRedirect();

    expect($board->fresh()->categories)->toBe([
        ['name' => 'Floor 1', 'display_ids' => [$display->id]],
        ['name' => 'Floor 2', 'display_ids' => []],
    ]);
});

test('groupDisplayData follows the configured order and puts ungrouped last', function () {
    [$lobby, $roomOne, $roomTwo] = Display::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ])->all();

    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'categories' => [
            ['name' => 'Floor 2', 'display_ids' => [$roomTwo->id]],
            ['name' => 'Floor 1', 'display_ids' => [$roomOne->id]],
            ['name' => 'Empty floor', 'display_ids' => []],
        ],
    ]);

    $groups = $board->groupDisplayData(collect([
        ['display' => $lobby],
        ['display' => $roomOne],
        ['display' => $roomTwo],
    ]));

    // Configured order wins, the empty category is not rendered, ungrouped is last with a null name.
    expect($groups->pluck('name')->all())->toBe(['Floor 2', 'Floor 1', null]);
    expect($groups[0]['displays']->pluck('display.id')->all())->toBe([$roomTwo->id]);
    expect($groups[1]['displays']->pluck('display.id')->all())->toBe([$roomOne->id]);
    expect($groups[2]['displays']->pluck('display.id')->all())->toBe([$lobby->id]);
});

test('groupDisplayData ignores display ids that are no longer on the board', function () {
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'categories' => [
            ['name' => 'Floor 1', 'display_ids' => ['a-deleted-display-id', $display->id]],
        ],
    ]);

    $groups = $board->groupDisplayData(collect([['display' => $display]]));

    expect($groups)->toHaveCount(1);
    expect($groups[0]['displays']->pluck('display.id')->all())->toBe([$display->id]);
});

/**
 * Put a single in-memory event on the display, the way EventService hands external calendar
 * events to the board (transient model, organizer_name set, user_id = the display owner).
 */
function fakeCurrentEvent(Display $display, ?string $organizerName, bool $asTabletBooking = false): void
{
    $event = new Event;
    $event->id = 'evt-'.$display->id;
    $event->display_id = $display->id;
    $event->user_id = $display->user_id;
    $event->external_id = 'external-1';
    $event->calendar_id = $asTabletBooking ? 'calendar-1' : null;
    $event->status = EventStatus::CONFIRMED;
    $event->summary = 'Sprint review';
    $event->start = now()->subMinutes(15);
    $event->end = now()->addMinutes(45);
    $event->organizer_name = $organizerName;

    $eventService = \Mockery::mock(EventService::class);
    $eventService->shouldReceive('getEventsForDisplay')->andReturn(collect([$event]));
    app()->instance(EventService::class, $eventService);
}

test('board shows the real calendar organizer instead of the workspace owner', function () {
    $this->user->update(['name' => 'Woningstichting Nijkerk']);

    $display = Display::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    fakeCurrentEvent($display, 'Jan de Vries');

    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'show_all_displays' => true,
        'show_booker' => true,
        'view_mode' => 'grid',
    ]);

    $response = $this->actingAs($this->user)->get(route('boards.show', $board));

    $response->assertStatus(200);
    $response->assertSee('Jan de Vries');
    $response->assertDontSee('Woningstichting Nijkerk');
});

test('board shows no organizer for a synced event without an organizer name', function () {
    $this->user->update(['name' => 'Woningstichting Nijkerk']);

    $display = Display::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    fakeCurrentEvent($display, null);

    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'show_all_displays' => true,
        'show_booker' => true,
        'view_mode' => 'grid',
    ]);

    $response = $this->actingAs($this->user)->get(route('boards.show', $board));

    $response->assertStatus(200);
    $response->assertDontSee('Woningstichting Nijkerk');
});

test('table view keeps a status column and shows the organizer inside the event columns', function () {
    $display = Display::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    fakeCurrentEvent($display, 'Jan de Vries');

    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'show_all_displays' => true,
        'show_booker' => true,
        'view_mode' => 'table',
    ]);

    $response = $this->actingAs($this->user)->get(route('boards.show', $board));

    $response->assertStatus(200);
    // Status has its own column again, and the organizer rides along in the event
    // columns instead of claiming one of its own.
    $response->assertSee('>Status<', false);
    $response->assertDontSee('>Organizer<', false);
    $response->assertSee('Jan de Vries');
});

test('board edit form renders the category editor with the stored layout', function () {
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
        'name' => 'Zaal 1',
    ]);

    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'categories' => [
            ['name' => 'Verdieping 1', 'display_ids' => [$display->id]],
        ],
    ]);

    $response = $this->actingAs($this->user)->get(route('boards.edit', $board));

    $response->assertStatus(200);
    $response->assertSee('Room Categories');
    $response->assertSee('Verdieping 1');
    $response->assertSee('boardCategories()', false);
});

test('board create form renders the category editor', function () {
    $response = $this->actingAs($this->user)->get(route('boards.create'));

    $response->assertStatus(200);
    $response->assertSee('Room Categories');
});

test('table view renders all category groups in one table so the columns line up', function () {
    [$first, $second] = Display::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ])->all();

    // A third display stays ungrouped, so we get three groups in total.
    Display::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $board = Board::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'show_all_displays' => true,
        'view_mode' => 'table',
        'categories' => [
            ['name' => 'Hoofdkamers', 'display_ids' => [$first->id]],
            ['name' => 'Subkamers', 'display_ids' => [$second->id]],
        ],
    ]);

    $html = $this->actingAs($this->user)->get(route('boards.show', $board))->assertStatus(200)->getContent();

    // Separate tables per group would each size their own columns, so the groups would not align.
    expect(substr_count($html, '<table'))->toBe(1);
    expect(substr_count($html, '<tbody'))->toBe(3);
    // Each group still repeats the header row.
    expect(substr_count($html, '>'.__('boards.room', [], 'en').'</th>'))->toBe(3);
});
