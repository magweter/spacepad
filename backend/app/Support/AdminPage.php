<?php

namespace App\Support;

use App\Models\RoadmapItem;
use App\Services\AdminStatsService;
use Illuminate\Http\Request;

/**
 * What every admin page gets on top of its own props: the portal shell with the Admin item
 * active, the figures shown above every admin screen and the number of roadmap suggestions that
 * wait for approval (shown on the Roadmap tab). Access is checked by the controllers
 * (ChecksAdminAccess), not here.
 */
class AdminPage
{
    /**
     * @return array<string, mixed>
     */
    public static function props(Request $request): array
    {
        return [
            'shell' => PortalShell::for($request, $request->user()->getSelectedWorkspace() ?? $request->user()->primaryWorkspace(), 'admin'),
            'stats' => app(AdminStatsService::class)->figures(),
            'pendingRoadmap' => RoadmapItem::where('is_approved', false)->count(),
        ];
    }
}
