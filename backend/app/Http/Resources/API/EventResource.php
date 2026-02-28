<?php

namespace App\Http\Resources\API;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $timezone = $this['timezone'] ?? config('app.timezone');
        // Convert from UTC (database) to the event's timezone
        // setTimezone() properly converts the time, unlike shiftTimezone() which only changes the label
        return [
            'id' => $this['id'],
            'status' => $this['status'],
            'summary' => $this['summary'],
            'location' => $this['location'],
            'description' => $this['description'],
            'start' => $this['start']->setTimezone($timezone)->toAtomString(),
            'end' => $this['end']->setTimezone($timezone)->toAtomString(),
            'checkedInAt' => $this['checked_in_at']?->toAtomString(),
            'timezone' => $this['timezone'],
            'checkInRequired' => $this->checkInRequired(),
            'source' => $this['source'] ?? null,
            'isTabletBooking' => $this->isTabletBooking(),
        ];
    }
}
