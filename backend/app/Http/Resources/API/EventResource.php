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
        // Support both custom Event model and external event arrays
        $event = $this->resource;
        if ($event instanceof Event) {
            return [
                'id' => $event->id,
                'summary' => $event->summary,
                'location' => null,
                'description' => null,
                'start' => $event->start->toAtomString(),
                'end' => $event->end->toAtomString(),
                'timezone' => config('app.timezone'),
                'isAllDay' => false,
            ];
        }

        $timezone = $this['timezone'] ?? config('app.timezone');
        return [
            'id' => $this['id'],
            'summary' => $this['summary'],
            'location' => $this['location'],
            'description' => $this['description'],
            'start' => Carbon::parse($this['start'])->setTimezone($timezone)->toAtomString(),
            'end' => Carbon::parse($this['end'])->setTimezone($timezone)->toAtomString(),
            'timezone' => $timezone,
            'isAllDay' => $this['isAllDay'],
        ];
    }
}
