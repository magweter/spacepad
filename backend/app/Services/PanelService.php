<?php

namespace App\Services;

use App\Models\Panel;
use App\Models\Display;
use Illuminate\Support\Facades\DB;

class PanelService
{
    public function createPanel(array $data): Panel
    {
        return DB::transaction(function () use ($data) {
            $panel = Panel::create([
                'user_id' => auth()->id(),
                'name' => $data['name'],
                'display_mode' => $data['display_mode'],
            ]);

            if (isset($data['displays']) && is_array($data['displays'])) {
                $this->attachDisplays($panel, $data['displays']);
            }

            return $panel;
        });
    }

    public function updatePanel(Panel $panel, array $data): Panel
    {
        return DB::transaction(function () use ($panel, $data) {
            $panel->update([
                'name' => $data['name'] ?? $panel->name,
                'display_mode' => $data['display_mode'] ?? $panel->display_mode,
            ]);

            if (isset($data['displays']) && is_array($data['displays'])) {
                $this->attachDisplays($panel, $data['displays']);
            }

            return $panel->fresh();
        });
    }

    public function attachDisplays(Panel $panel, array $displayIds): void
    {
        // Validate max 4 displays
        if (count($displayIds) > 4) {
            throw new \InvalidArgumentException('Maximum 4 displays allowed per panel');
        }

        // Validate user owns all displays
        $userDisplays = Display::where('user_id', auth()->id())
            ->whereIn('id', $displayIds)
            ->pluck('id')
            ->toArray();

        if (count($userDisplays) !== count($displayIds)) {
            throw new \InvalidArgumentException('One or more displays do not belong to you');
        }

        // Detach all existing displays
        $panel->displays()->detach();

        // Attach new displays with order
        foreach ($displayIds as $index => $displayId) {
            $panel->displays()->attach($displayId, ['order' => $index]);
        }
    }

    public function detachDisplay(Panel $panel, string $displayId): void
    {
        $panel->displays()->detach($displayId);
    }

    public function reorderDisplays(Panel $panel, array $displayIds): void
    {
        // Validate user owns all displays
        $userDisplays = Display::where('user_id', auth()->id())
            ->whereIn('id', $displayIds)
            ->pluck('id')
            ->toArray();

        if (count($userDisplays) !== count($displayIds)) {
            throw new \InvalidArgumentException('One or more displays do not belong to you');
        }

        // Detach all and reattach with new order
        $panel->displays()->detach();
        foreach ($displayIds as $index => $displayId) {
            $panel->displays()->attach($displayId, ['order' => $index]);
        }
    }

    public function getPanelData(string $panelId): array
    {
        $panel = Panel::with(['displays.calendar', 'displays.settings'])
            ->findOrFail($panelId);

        $eventService = app(EventService::class);

        // Get events for each display
        $displaysData = [];
        foreach ($panel->displays as $display) {
            $events = $eventService->getEventsForDisplay($display->id);
            $displaysData[] = [
                'display' => $display,
                'events' => $events,
            ];
        }

        return [
            'panel' => $panel,
            'displays' => $displaysData,
        ];
    }
}

