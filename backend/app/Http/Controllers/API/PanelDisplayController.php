<?php

namespace App\Http\Controllers\API;

use App\Models\Panel;
use App\Services\PanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelDisplayController extends ApiController
{
    public function __construct(
        protected PanelService $panelService
    ) {
    }

    public function attachDisplay(Request $request, string $panelId): JsonResponse
    {
        $request->validate([
            'display_id' => ['required', 'ulid', 'exists:displays,id'],
        ]);

        $panel = Panel::where('user_id', auth()->id())
            ->findOrFail($panelId);

        $currentCount = $panel->displays()->count();
        if ($currentCount >= 4) {
            return $this->error('Maximum 4 displays allowed per panel', 400);
        }

        $this->panelService->attachDisplays($panel, [
            ...$panel->displays()->pluck('displays.id')->toArray(),
            $request->input('display_id'),
        ]);

        return $this->success(message: 'Display attached successfully');
    }

    public function detachDisplay(string $panelId, string $displayId): JsonResponse
    {
        $panel = Panel::where('user_id', auth()->id())
            ->findOrFail($panelId);

        $this->panelService->detachDisplay($panel, $displayId);

        return $this->success(message: 'Display detached successfully');
    }

    public function reorderDisplays(Request $request, string $panelId): JsonResponse
    {
        $request->validate([
            'display_ids' => ['required', 'array', 'min:1', 'max:4'],
            'display_ids.*' => ['required', 'ulid', 'exists:displays,id'],
        ]);

        $panel = Panel::where('user_id', auth()->id())
            ->findOrFail($panelId);

        $this->panelService->reorderDisplays($panel, $request->input('display_ids'));

        return $this->success(message: 'Displays reordered successfully');
    }
}

