<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
    $this->admin = User::factory()->active()->create(['is_admin' => true]);
});

test('every top-level admin screen carries the same tiles and tab bar', function () {
    // The Inertia screens get the figures and the pending roadmap count for AdminTabs, with the
    // Admin item active in the shell.
    foreach ([
        route('admin.index') => ['Admin/Users'],
        route('admin.workspaces.index') => ['Admin/Workspaces'],
        route('admin.merge.index') => ['Admin/Merge'],
        route('admin.roadmap.index') => ['Admin/Roadmap'],
    ] as $url => [$component]) {
        $this->actingAs($this->admin)->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)
                ->has('shell')
                ->has('stats.total_users')
                ->has('stats.active_workspaces')
                ->has('stats.active_instances')
                ->has('stats.total_instances')
                ->has('pendingRoadmap'));
    }
});

test('a detail page is laid out as a drill-down, like the user page it mirrors', function () {
    $workspace = $this->admin->primaryWorkspace();

    // No tab bar: it is reached from a list, and offers the way back up that the user detail
    // page does.
    $this->actingAs($this->admin)
        ->get(route('admin.workspaces.show', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Workspace')
            ->has('shell')
            ->where('urls.index', route('admin.workspaces.index')));

    expect(file_get_contents(resource_path('js/Pages/Admin/Workspace.vue')))
        ->toContain('Back to Workspaces')
        ->not->toContain('<AdminTabs');

    // The user page gets the shell only, no figures or tab counts, and the way back up.
    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $this->admin))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/User')
            ->has('shell')
            ->missing('stats')
            ->missing('pendingRoadmap')
            ->where('urls.back', route('admin.index')));
});

test('the workspace pages mark the Workspaces tab as the active one', function () {
    expect(file_get_contents(resource_path('js/Pages/Admin/Workspaces.vue')))
        ->toContain('<AdminTabs active="workspaces"');
});

test('the billing fields are drawn with a visible border', function () {
    // Tailwind v4 without the forms plugin strips the native control border, so a colour
    // alone renders nothing. Both fields must ask for a border width too.
    $form = file_get_contents(resource_path('js/Pages/Admin/Partials/WorkspaceBillingForm.vue'));

    preg_match("/const fieldClass = '([^']*)'/", $form, $fieldClass);
    preg_match('/<input[^>]*name="manual_billing_unit_price"[^>]*>/', $form, $input);
    preg_match('/<select[^>]*name="billing_owner_user_id"[^>]*>/', $form, $select);

    expect($fieldClass[1])->toContain('border border-line')
        ->and($input[0])->toContain(':class="[fieldClass')
        ->and($select[0])->toContain(':class="[fieldClass');
});

test('the users list names the plan of the workspaces someone owns, manual and unlimited included', function () {
    $manual = User::factory()->active()->create(['email' => 'manual@example.com']);
    $manual->primaryWorkspace()->update(['is_manually_billed' => true]);
    $unlimited = User::factory()->active()->create(['email' => 'unlimited@example.com']);
    $unlimited->primaryWorkspace()->update(['is_unlimited' => true]);
    User::factory()->active()->create(['email' => 'free@example.com']);

    $this->actingAs($this->admin)->get(route('admin.index'))
        ->assertInertia(function (Assert $page) {
            $plans = collect($page->toArray()['props']['users'])->pluck('plan', 'email');
            expect($plans['manual@example.com'])->toBe(['key' => 'manual', 'label' => 'Manually billed'])
                ->and($plans['unlimited@example.com'])->toBe(['key' => 'unlimited', 'label' => 'Unlimited'])
                ->and($plans['free@example.com'])->toBe(['key' => 'free', 'label' => 'Free']);
        });
});
