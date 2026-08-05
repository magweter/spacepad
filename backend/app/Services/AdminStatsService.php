<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The figures on the tiles at the top of every admin screen.
 *
 * Shared rather than resolved per controller so the tiles read the same on whichever admin
 * page you happen to be on. Cached briefly: they are a glance at the business, not a live
 * readout, and they would otherwise run on every page of every table.
 */
class AdminStatsService
{
    /**
     * @return array{total_users: int, active_workspaces: int, active_instances: int, total_instances: int}
     */
    public function figures(): array
    {
        return cache()->remember('admin:stats', now()->addMinute(), function () {
            return [
                'total_users' => User::whereNull('deleted_at')->count(),
                'active_workspaces' => $this->fromAnalytics(
                    'analytics_workspaces',
                    'COUNT(CASE WHEN last_device_activity_at >= ? THEN 1 END)',
                ),
                'active_instances' => $this->fromAnalytics(
                    'analytics_instances',
                    'COUNT(CASE WHEN last_heartbeat_at >= ? THEN 1 END)',
                ),
                'total_instances' => $this->fromAnalytics('analytics_instances', 'COUNT(*)', withCutoff: false),
            ];
        });
    }

    /**
     * One figure from a snapshot table, or zero when there is no snapshot to read.
     *
     * The analytics tables do not exist on a self-hosted install, and on cloud they are
     * empty until the first scheduled refresh. Neither is a reason for the admin panel to
     * fall over.
     */
    private function fromAnalytics(string $table, string $expression, bool $withCutoff = true): int
    {
        try {
            return (int) DB::table($table)
                ->selectRaw("{$expression} as figure", $withCutoff ? [now()->subDays(7)] : [])
                ->value('figure');
        } catch (\Exception $e) {
            return 0;
        }
    }
}
