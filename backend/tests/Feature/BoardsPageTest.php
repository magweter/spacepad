<?php

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

function proBoardsUser(): User
{
    $user = User::factory()->active()->unlimited()->create();
    session()->put('selected_workspace_id', $user->primaryWorkspace()->id);

    return $user;
}

test('a free workspace sees the page with the Pro upsell instead of an error', function () {
    $user = User::factory()->active()->create();
    Board::factory()->create(['workspace_id' => $user->primaryWorkspace()->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('boards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Boards/Index')
            ->where('hasPro', false)
            ->where('canCreate', false)
            ->where('boards', [])
            ->where('board', null)
            ->where('urls.upgrade', route('billing.index')));
});

test('a free workspace is sent to the upsell from create and edit', function () {
    $user = User::factory()->active()->create();
    $board = Board::factory()->create(['workspace_id' => $user->primaryWorkspace()->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('boards.create'))->assertRedirect(route('boards.index'));
    $this->actingAs($user)->get(route('boards.edit', $board))->assertRedirect(route('boards.index'));
});

test('the list shows each board and opens the first one in the editor', function () {
    $user = proBoardsUser();
    $workspace = $user->primaryWorkspace();
    $room = Display::factory()->create(['workspace_id' => $workspace->id, 'status' => DisplayStatus::ACTIVE, 'name' => 'Boardroom', 'display_name' => 'Boardroom']);
    Display::factory()->create(['workspace_id' => $workspace->id, 'status' => DisplayStatus::DEACTIVATED, 'name' => 'Old room']);

    $first = Board::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'name' => 'A lobby', 'view_mode' => 'table', 'show_all_displays' => false]);
    $first->displays()->sync([$room->id]);
    Board::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'name' => 'B floor', 'show_all_displays' => true]);

    $this->actingAs($user)->get(route('boards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Boards/Index')
            ->where('hasPro', true)
            ->where('canCreate', true)
            ->where('shell.nav.2.active', true)
            ->has('boards', 2)
            ->where('boards.0.name', 'A lobby')
            ->where('boards.0.viewMode', 'table')
            ->where('boards.0.roomCount', 1)
            ->where('boards.0.editUrl', route('boards.edit', $first))
            ->where('boards.1.showAll', true)
            ->where('board.id', $first->id)
            ->where('board.display_ids', [$room->id])
            ->where('board.updateUrl', route('boards.update', $first))
            ->has('displays', 1)
            ->where('displays.0.name', 'Boardroom'));
});

test('the editor shows the public link and opens it once after it was switched on', function () {
    $user = proBoardsUser();
    $board = Board::factory()->create([
        'workspace_id' => $user->primaryWorkspace()->id, 'user_id' => $user->id,
        'is_public' => true, 'public_token' => 'tok123',
    ]);

    $this->actingAs($user)->get(route('boards.edit', ['board' => $board, 'show_public' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.publicUrl', route('boards.public', 'tok123'))
            ->where('boards.0.isPublic', true)
            ->where('showPublic', true));

    $this->actingAs($user)->get(route('boards.edit', $board))
        ->assertInertia(fn (Assert $page) => $page->where('showPublic', false));
});

test('creating a public board lands on its editor with the link popup', function () {
    $user = proBoardsUser();

    $response = $this->actingAs($user)->post(route('boards.store'), [
        'name' => 'Lobby',
        'workspace_id' => $user->primaryWorkspace()->id,
        'show_all_displays' => '1',
        'is_public' => '1',
    ]);

    $board = Board::where('name', 'Lobby')->firstOrFail();
    $response->assertRedirect(route('boards.edit', ['board' => $board, 'show_public' => 1]));
    expect($board->public_token)->not->toBeNull();
});

test('the editor posts as the form data the board form request expects', function () {
    $user = proBoardsUser();
    $workspace = $user->primaryWorkspace();
    $room = Display::factory()->create(['workspace_id' => $workspace->id, 'status' => DisplayStatus::ACTIVE]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'show_title' => true]);

    // As Inertia sends a form with a file: multipart, booleans as "1"/"0", PUT spoofed via _method.
    $this->actingAs($user)->post(route('boards.update', $board), [
        '_method' => 'put',
        'workspace_id' => $workspace->id,
        'name' => 'Renamed',
        'title' => '',
        'show_all_displays' => '0',
        'display_ids' => [$room->id],
        'show_title' => '0',
        'show_booker' => '1',
        'show_next_event' => '1',
        'show_meeting_title' => '1',
        'show_join_button' => '0',
        'show_transitioning' => '1',
        'transitioning_minutes' => '5',
        'view_mode' => 'grid',
        'theme' => 'system',
        'font_family' => 'Lato',
        'language' => 'nl',
        'is_public' => '0',
        'logo' => '',
        'remove_logo' => '0',
        'categories' => [['name' => 'Floor 1', 'display_ids' => [$room->id]]],
    ])->assertRedirect(route('boards.edit', $board));

    $board->refresh();
    expect($board->name)->toBe('Renamed')
        ->and($board->show_title)->toBeFalse()
        ->and($board->view_mode)->toBe('grid')
        ->and($board->theme)->toBe('system')
        ->and($board->transitioning_minutes)->toBe(5)
        ->and($board->displays->pluck('id')->all())->toBe([$room->id])
        ->and($board->categories)->toBe([['name' => 'Floor 1', 'display_ids' => [$room->id]]]);
});
