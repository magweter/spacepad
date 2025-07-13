<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisplaySettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'check_in_enabled' => $this->isCheckInEnabled(),
            'booking_enabled' => $this->isBookingEnabled(),
            'calendar_enabled' => $this->isCalendarEnabled(),
            'check_in_minutes' => $this->getCheckInMinutes(),
            'check_in_grace_period' => $this->getCheckInGracePeriod(),
        ];
    }
}
