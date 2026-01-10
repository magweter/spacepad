<?php

namespace App\Services;

use App\Data\PermissionResult;
use App\Models\Device;
use App\Models\Display;

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
        $device = Device::query()->find($deviceId);
        if (!$device) {
            return new PermissionResult(false, 'Device not found', 404);
        }

        if (!$device->workspace_id) {
            return new PermissionResult(false, 'Device not associated with a workspace', 404);
        }

        $workspaceId = $device->workspace_id;

        // Find display by ID, checking workspace access
        $display = null;
        if ($displayId) {
            $display = Display::with('workspace.members')->find($displayId);
            
            if ($display) {
                // Check access: device and display must be in the same workspace
                if (!$display->workspace_id || $display->workspace_id !== $workspaceId) {
                    return new PermissionResult(false, 'Display not found', 404);
                }
            }
        }

        if (!$display) {
            return new PermissionResult(false, 'Display not found', 404);
        }
        if ($display->isDeactivated()) {
            return new PermissionResult(false, 'Display is deactivated', 400);
        }
        
        // Check Pro feature - check workspace owners
        if (!empty($options['pro'])) {
            $workspace = $display->workspace;
            $hasPro = false;
            
            if ($workspace) {
                $owners = $workspace->owners()->get();
                foreach ($owners as $owner) {
                    if ($owner->hasPro()) {
                        $hasPro = true;
                        break;
                    }
                }
            }
            
            if (!$hasPro) {
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
