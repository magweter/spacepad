<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PanelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_mode' => $this->display_mode->value,
            'displays_count' => $this->whenCounted('displays'),
            'displays' => DisplayResource::collection($this->whenLoaded('displays')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

