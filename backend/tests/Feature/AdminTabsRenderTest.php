<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->admin = User::factory()->active()->create(['is_admin' => true]);
});

test('every top-level admin screen carries the same tiles and tab bar', function () {
    foreach ([
        route('admin.index'),
        route('admin.workspaces.index'),
        route('admin.merge.index'),
    ] as $url) {
        $this->actingAs($this->admin)->get($url)->assertOk()
            // The tiles.
            ->assertSee('Total Users')
            ->assertSee('Active Workspaces')
            ->assertSee('Active Instances')
            ->assertSee('Total Instances')
            // The tabs.
            ->assertSee('Workspaces')
            ->assertSee('Merge workspaces')
            ->assertSee('Roadmap');
    }
});

test('a detail page is laid out as a drill-down, like the user page it mirrors', function () {
    $workspace = $this->admin->primaryWorkspace();

    // No tab bar and no tiles: it is reached from a list, and offers the way back up that
    // the user detail page does.
    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.show', $workspace))
        ->assertOk()
        ->assertDontSee('Merge workspaces')
        ->assertDontSee('Total Instances')
        ->assertSee('Back to Workspaces');

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $this->admin))
        ->assertOk()
        ->assertDontSee('Merge workspaces')
        ->assertSee('Back to Admin');
});

test('the workspace pages mark the Workspaces tab as the active one', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('admin.workspaces.index'))
        ->assertOk()
        ->getContent();

    preg_match('/<a[^>]*admin\/workspaces"[^>]*>/', $html, $link);

    expect($link[0])->toContain('border-blue-500 text-blue-600');
});

test('the billing fields are drawn with a visible border', function () {
    $workspace = $this->admin->primaryWorkspace();

    $html = $this->actingAs($this->admin)
        ->get(route('admin.workspaces.show', $workspace))
        ->assertOk()
        ->getContent();

    // Tailwind v4 without the forms plugin strips the native control border, so a colour
    // alone renders nothing. Both fields must ask for a border width too.
    preg_match('/<input[^>]*name="manual_billing_unit_price"[^>]*>/', $html, $input);
    preg_match('/<select[^>]*name="billing_owner_user_id"[^>]*>/', $html, $select);

    expect($input[0])->toContain('border border-gray-300');
    expect($select[0])->toContain('border border-gray-300');
});
