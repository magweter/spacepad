<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PanelDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $displaysData = [];
        foreach ($this->resource['displays'] ?? [] as $displayData) {
            $displaysData[] = [
                'display' => DisplayResource::make($displayData['display']),
                'events' => EventResource::collection($displayData['events'] ?? []),
            ];
        }

        return [
            'panel' => PanelResource::make($this->resource['panel'] ?? null),
            'displays' => $displaysData,
        ];
    }
}

