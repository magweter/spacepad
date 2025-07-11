<?php

namespace App\Services;

use App\Data\PermissionResult;
use App\Models\Device;
use App\Models\Display;

class DisplayService
{
    public function getDisplay(string $displayId)
    {
        return Display::query()->findOrFail($displayId);
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
        $userId = Device::query()->where('id', $deviceId)->value('user_id');
        $display = $displayId ? Display::with('user')->where('user_id', $userId)->find($displayId) : null;

        if (!$display) {
            return new PermissionResult(false, 'Display not found', 404);
        }
        if ($display->isDeactivated()) {
            return new PermissionResult(false, 'Display is deactivated', 400);
        }
        if (!empty($options['pro']) && (!$display->user || !$display->user->hasPro())) {
            return new PermissionResult(false, 'This is a Pro feature. Please upgrade to Pro to use this feature.', 403);
        }
        if (!empty($options['booking']) && !$display->isBookingEnabled()) {
            return new PermissionResult(false, 'Booking is not enabled for this display', 403);
        }

        // Add more checks as needed
        return new PermissionResult(true);
    }
}
