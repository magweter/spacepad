<?php

namespace App\Http\Controllers;

use App\Services\OutlookService;
use App\Services\GoogleService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class RoomController extends Controller
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService
    ) {
    }

    public function outlook(string $id): View|Factory|Application
    {
        $account = auth()->user()->outlookAccounts()->findOrFail($id);
        return view('components.rooms.picker', [
            'rooms' => $account->getRooms()
        ]);
    }

    public function google(string $id): View|Factory|Application
    {
        $account = auth()->user()->googleAccounts()->findOrFail($id);
        return view('components.rooms.picker', [
            'rooms' => $account->getRooms()
        ]);
    }
}
