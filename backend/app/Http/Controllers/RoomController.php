<?php

namespace App\Http\Controllers;

use App\Services\OutlookService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class RoomController extends Controller
{
    public function __construct(protected OutlookService $outlookService)
    {
    }

    public function outlook(string $id): View|Factory|Application
    {
        $account = auth()->user()->outlookAccounts()->findOrFail($id);
        return view('components.rooms.outlook', [
            'rooms' => $account->getRooms()
        ]);
    }
}
