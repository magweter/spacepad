<?php

use App\Enums\DisplayStatus;
use App\Enums\UsageType;
use App\Helpers\DisplaySettings;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->unlimited()->create();
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);

    $this->display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
        'name' => 'Boardroom tablet',
        'display_name' => 'Boardroom',
    ]);
});

test('the configure page gets the display, its values and the urls it posts to', function () {
    Device::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'display_id' => $this->display->id,
        'last_activity_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Configure/Index')
            ->where('display.name', 'Boardroom tablet')
            ->where('display.roomName', 'Boardroom')
            ->where('display.device.online', true)
            ->where('values.cancel_permission', 'all')
            ->where('values.font_size_title', 'normal')
            ->where('images.logo.url', null)
            ->has('options.fontSizeElements', count(DisplaySettings::FONT_SIZE_ELEMENTS))
            ->where('urls.rename', route('displays.update', $this->display))
            ->where('urls.back', route('displays.index')));
});

test('a section saves through the multipart post the page sends', function () {
    Storage::fake('public');

    // The page posts multipart with _method=put, so uploads work; booleans arrive as 1 and 0.
    $this->actingAs($this->user)
        ->post(route('displays.section.update', ['display' => $this->display, 'section' => 'branding']), [
            '_method' => 'put',
            'font_family' => 'Lato',
            'font_size_title' => 'large',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'remove_logo' => '0',
            'background_image' => '',
            'remove_background_image' => '0',
            'default_background' => '',
        ])
        ->assertRedirect(route('displays.configure', $this->display));

    $fresh = $this->display->fresh();
    expect(DisplaySettings::getFontFamily($fresh))->toBe('Lato')
        ->and(DisplaySettings::getFontSize($fresh, 'title'))->toBe('large')
        ->and(DisplaySettings::getLogo($fresh))->toStartWith('displays/logos/')
        ->and(DisplaySettings::getBackgroundImage($fresh))->toBeNull();

    $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertInertia(fn (Assert $page) => $page->where('images.logo.own', true)->where('values.font_family', 'Lato'));
});

test('a bundled background is picked without an upload', function () {
    $this->actingAs($this->user)
        ->post(route('displays.section.update', ['display' => $this->display, 'section' => 'branding']), [
            '_method' => 'put',
            'font_family' => 'Inter',
            'default_background' => 'default_2',
        ])
        ->assertRedirect();

    $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertInertia(fn (Assert $page) => $page->where('images.background.default', 'default_2')
            ->where('images.background.url', asset('images/backgrounds/default_2.jpg')));
});

test('a validation error comes back to the page', function () {
    $this->actingAs($this->user)
        ->from(route('displays.configure', $this->display))
        ->post(route('displays.section.update', ['display' => $this->display, 'section' => 'behavior']), [
            '_method' => 'put',
            'check_in_minutes' => 500,
        ])
        ->assertRedirect(route('displays.configure', $this->display))
        ->assertSessionHasErrors('check_in_minutes');
});

test('a free workspace is sent back to the overview', function () {
    $free = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);
    session()->put('selected_workspace_id', $free->primaryWorkspace()->id);
    $display = Display::factory()->create(['workspace_id' => $free->primaryWorkspace()->id, 'user_id' => $free->id]);

    $this->actingAs($free)
        ->get(route('displays.configure', $display))
        ->assertRedirect(route('dashboard'));
});

test('a display in another workspace cannot be configured', function () {
    $other = User::factory()->active()->unlimited()->create();
    $display = Display::factory()->create(['workspace_id' => $other->primaryWorkspace()->id, 'user_id' => $other->id]);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertForbidden();
});
