<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'start' => $this['start'],
            'end' => $this['end'],
            'timezone' => $this['timezone'],
            'isAllDay' => $this['isAllDay'],
        ];
    }
}
