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
        ];
    }
}
