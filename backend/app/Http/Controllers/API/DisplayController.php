<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\DisplayResource;
use App\Models\Display;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DisplayController extends Controller
{
    public function getDisplays(): AnonymousResourceCollection
    {
        $displays = Display::query()
            ->where('user_id', auth()->user()->user_id)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->get();

        return DisplayResource::collection($displays);
    }
}
