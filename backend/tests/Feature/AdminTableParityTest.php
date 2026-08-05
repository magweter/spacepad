<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The workspaces overview must be indistinguishable from the users overview in everything
 * but its columns. Asserted on the rendered HTML rather than on the templates, so it still
 * holds if either page is restyled through a shared component.
 */
test('both overviews draw their table, cells and empty state the same way', function () {
    config(['settings.is_self_hosted' => false]);
    $admin = User::factory()->active()->create(['is_admin' => true]);

    $users = $this->actingAs($admin)->get(route('admin.index'))->assertOk()->getContent();
    $workspaces = $this->actingAs($admin)->get(route('admin.workspaces.index'))->assertOk()->getContent();

    foreach ([
        // The card the table sits in.
        'bg-white shadow rounded-lg p-6',
        // The search field.
        'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border',
        // The table itself.
        'min-w-full divide-y divide-gray-300',
        'divide-y divide-gray-200',
        // Header cells, first and subsequent.
        'py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0',
        'px-3 py-3.5 text-left text-sm font-semibold text-gray-900',
        // Body cells, first and subsequent.
        'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0',
        'whitespace-nowrap px-3 py-4 text-sm text-gray-500',
        // Badges.
        'inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20',
        // The action link.
        'text-blue-600 hover:text-blue-900 font-medium',
    ] as $class) {
        expect($users)->toContain($class);
        expect($workspaces)->toContain($class);
    }
});
