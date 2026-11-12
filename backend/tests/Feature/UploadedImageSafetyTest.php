<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function boardForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lobby',
        'workspace_id' => test()->workspace->id,
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

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->active()->unlimited()->create();
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);
});

/**
 * An SVG is a document, not a picture: it can carry script, and a browser that opens the
 * logo URL directly runs that script on the app's origin. The public board logo makes the
 * same file reachable without a login.
 */
test('a board logo cannot be an svg', function () {
    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>'
    );

    $this->actingAs($this->user)
        ->post(route('boards.store'), boardForm(['logo' => $svg]))
        ->assertSessionHasErrors('logo');

    expect(Board::count())->toBe(0);
});

test('an existing board cannot be given an svg logo', function () {
    $board = Board::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

    $this->actingAs($this->user)
        ->put(route('boards.update', $board), boardForm(['logo' => $svg]))
        ->assertSessionHasErrors('logo');

    expect($board->fresh()->logo)->toBeNull();
});

/**
 * Whatever gets through, a served upload must never run as a page on our origin.
 */
test('a served board logo is sandboxed and not sniffed', function () {
    $board = Board::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->put(route('boards.update', $board), boardForm(['logo' => UploadedFile::fake()->image('logo.png')]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->user)
        ->get(route('boards.images.logo', $board))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', 'sandbox')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

/**
 * The stored name follows what the file is, not what the client called it. With a linked
 * public/storage a web server types by extension, so a PNG called logo.html would be served
 * as a page.
 */
test('a stored upload takes its extension from the content, not the client file name', function () {
    $board = Board::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);

    // A real PNG that the client calls logo.html.
    $png = UploadedFile::fake()->image('real.png');
    $misnamed = new UploadedFile($png->getPathname(), 'logo.html', 'image/png', null, true);

    $this->actingAs($this->user)
        ->put(route('boards.update', $board), boardForm(['logo' => $misnamed]))
        ->assertSessionHasNoErrors();

    expect($board->fresh()->logo)->toEndWith('.png');
});
