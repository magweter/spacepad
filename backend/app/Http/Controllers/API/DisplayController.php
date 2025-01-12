<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\DisplayResource;
use App\Models\Display;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DisplayController extends Controller
{
    public function getDisplays(): AnonymousResourceCollection
    {
        $displays = Display::where('user_id', auth()->user()->user_id)->get();
        return DisplayResource::collection($displays);
    }
}
