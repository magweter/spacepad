<?php

namespace App\Services;

use App\Data\PermissionResult;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;

class DisplayService
{
    public function getDisplay(string $displayId)
    {
        return Display::query()->with('settings')->findOrFail($displayId);
    }

    /**
     * Validate if a display is permitted to perform actions.
     *
     * @param string|null $displayId
     * @param string $deviceId
     * @param array $options ['pro' => true, 'booking' => true]
     * @return PermissionResult
     */
    public function validateDisplayPermission(?string $displayId, string $deviceId, array $options = []): PermissionResult
    {
        $device = Device::with('user.workspaces')->find($deviceId);
        
        if (!$device || !$device->user_id) {
            return new PermissionResult(false, 'Device not found', 404);
        }

        $user = $device->user;
        if (!$user) {
            return new PermissionResult(false, 'User not found', 404);
        }

        if (!$displayId) {
            return new PermissionResult(false, 'Display not found', 404);
        }

        // Get all workspace IDs the user is a member of
        $workspaceIds = $user->workspaces->pluck('id');
        if ($workspaceIds->isEmpty()) {
            return new PermissionResult(false, 'User is not a member of any workspace', 403);
        }

        // Find display in any of the user's workspaces
        $display = Display::with('workspace.members')
            ->whereIn('workspace_id', $workspaceIds)
            ->find($displayId);

        if (!$display) {
            return new PermissionResult(false, 'Display not found', 404);
        }
        
        if ($display->isDeactivated()) {
            return new PermissionResult(false, 'Display is deactivated', 400);
        }
        
        // Pro feature check: check if any workspace owner has Pro
        if (!empty($options['pro'])) {
            if (!$display->workspace->hasPro()) {
                return new PermissionResult(false, 'This is a Pro feature. Please upgrade to Pro to use this feature.', 403);
            }
        }
        
        if (!empty($options['booking']) && !$display->isBookingEnabled()) {
            return new PermissionResult(false, 'Booking is not enabled for this display', 403);
        }

        // Add more checks as needed
        return new PermissionResult(true);
    }
}
