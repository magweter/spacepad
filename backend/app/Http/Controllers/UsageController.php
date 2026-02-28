<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;

class UsageController extends Controller
{
    /**
     * Display the usage page
     */
    public function index(): View|Factory|Application
    {
        $user = auth()->user();
        $selectedWorkspace = $user->getSelectedWorkspace();
        
        if (!$selectedWorkspace) {
            abort(404, 'No workspace found');
        }
        
        $usageBreakdown = $selectedWorkspace->getUsageBreakdown();
        
        return view('pages.usage.index', [
            'workspace' => $selectedWorkspace,
            'usageBreakdown' => $usageBreakdown,
        ]);
    }
}
