<?php

namespace App\Http\Resources\API;

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
        $timezone = $this['timezone'] ?? 'UTC';
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
