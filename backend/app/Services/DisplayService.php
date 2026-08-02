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
        return Display::query()->with(['settings', 'profile.settings'])->findOrFail($displayId);
    }

    /**
     * Validate if a display is permitted to perform actions.
     *
     * @param  array  $options  ['pro' => true, 'booking' => true]
     */
    public function validateDisplayPermission(?string $displayId, string $deviceId, array $options = []): PermissionResult
    {
        $device = Device::with('user.workspaces')->find($deviceId);

        if (! $device || ! $device->user_id) {
            return new PermissionResult(false, 'Device not found', 404);
        }

        $user = $device->user;
        if (! $user) {
            return new PermissionResult(false, 'User not found', 404);
        }

        if (! $displayId) {
            return new PermissionResult(false, 'Display not found', 404);
        }

        // Scope to the device's own workspace. Using every workspace the pairing user
        // belongs to would let a tablet paired for one workspace reach another team's
        // displays as soon as that person joined a second workspace. Devices paired before
        // workspace_id was set fall back to the old behaviour.
        $workspaceIds = $device->workspace_id
            ? collect([$device->workspace_id])
            : $user->workspaces->pluck('id');

        if ($workspaceIds->isEmpty()) {
            return new PermissionResult(false, 'User is not a member of any workspace', 403);
        }

        $display = Display::with('workspace.members')
            ->whereIn('workspace_id', $workspaceIds)
            ->find($displayId);

        if (! $display) {
            return new PermissionResult(false, 'Display not found', 404);
        }

        if ($display->isDeactivated()) {
            return new PermissionResult(false, 'Display is deactivated', 400);
        }

        // Pro feature check: check if any workspace owner has Pro
        if (! empty($options['pro'])) {
            if (! $display->workspace->hasPro()) {
                return new PermissionResult(false, 'This is a Pro feature. Please upgrade to Pro to use this feature.', 403);
            }
        }

        if (! empty($options['booking']) && ! $display->isBookingEnabled()) {
            return new PermissionResult(false, 'Booking is not enabled for this display', 403);
        }

        // Add more checks as needed
        return new PermissionResult(true);
    }
}
