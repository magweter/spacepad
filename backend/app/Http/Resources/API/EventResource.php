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
        return [
            'id' => $this['id'],
            'summary' => $this['summary'],
            'location' => $this['location'],
            'description' => $this['description'],
            'start' => Carbon::parse($this['start'])->setTimezone($this['timezone'])->toAtomString(),
            'end' => Carbon::parse($this['end'])->setTimezone($this['timezone'])->toAtomString(),
            'timezone' => $this['timezone'],
            'isAllDay' => $this['isAllDay'],
        ];
    }
}
