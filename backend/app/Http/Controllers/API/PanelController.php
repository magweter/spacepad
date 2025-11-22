<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\API\PanelDataResource;
use App\Http\Resources\API\PanelResource;
use App\Models\Panel;
use App\Services\PanelService;
use Illuminate\Http\JsonResponse;

class PanelController extends ApiController
{
    public function __construct(
        protected PanelService $panelService
    ) {
    }

    public function index(): JsonResponse
    {
        $panels = Panel::where('user_id', auth()->id())
            ->withCount('displays')
            ->get();

        return $this->success(data: PanelResource::collection($panels));
    }

    public function show(string $panelId): JsonResponse
    {
        $panel = Panel::where('user_id', auth()->id())
            ->findOrFail($panelId);

        return $this->success(data: PanelResource::make($panel));
    }

    public function getPanelData(string $panelId): JsonResponse
    {
        $panel = Panel::where('user_id', auth()->id())
            ->findOrFail($panelId);

        $data = $this->panelService->getPanelData($panel->id);

        return $this->success(data: PanelDataResource::make($data));
    }
}

