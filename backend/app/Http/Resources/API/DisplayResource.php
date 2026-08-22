<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisplayResource extends JsonResource
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
            // The room name, printed in the header corner. Kept on 'name' because tablets
            // already in the field read it from there and update on their own schedule.
            'name' => $this->display_name,
            // The "Display name" from the portal: dashboard-only and unique per display. Named
            // 'dashboard_name' here because the display_name column is the room name above.
            // The setup wizard lists this one, since room names are shared on purpose in setups
            // that put a building name there.
            'dashboard_name' => $this->name,
            'settings' => DisplaySettingsResource::make($this),
        ];
    }
}
