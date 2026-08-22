{{--
    The tiles at the top of every admin screen.

    Figures come from AdminStatsService through a view composer, so no admin controller has
    to remember to supply them and none of them can quietly disagree.
--}}
<div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <dt class="text-xs font-medium text-gray-500 truncate">Total Users</dt>
        <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['total_users'] }}</dd>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <dt class="text-xs font-medium text-gray-500 truncate">Active Workspaces</dt>
        <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['active_workspaces'] }}</dd>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <dt class="text-xs font-medium text-gray-500 truncate">Active Instances</dt>
        <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['active_instances'] }}</dd>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <dt class="text-xs font-medium text-gray-500 truncate">Total Instances</dt>
        <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['total_instances'] }}</dd>
    </div>
</div>
