<?php

namespace App\Http\Controllers;

use App\Models\Display;
use App\Models\Instance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() || config('settings.is_self_hosted')) {
            abort(403);
        }

        $activeDisplays = Display::where('status', 'active')->count();
        $totalDisplays = Display::count();
        $totalInstances = \App\Models\Instance::count();
        $sevenDaysAgo = now()->subDays(7);

        // Active self-hosted instances in the last 7 days, sorted by registration order
        $activeInstances = \App\Models\Instance::where('is_self_hosted', true)
            ->where('last_heartbeat_at', '>=', $sevenDaysAgo)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($instance) {
                $instance->is_paid = (bool) $instance->license_valid;
                return $instance;
            });

        // Active cloud-hosted users: users with at least one display active in the last 7 days, sorted by registration order
        $activeUsers = \App\Models\User::whereHas('displays', function($q) use ($sevenDaysAgo) {
                $q->where('last_event_at', '>=', $sevenDaysAgo);
            })
            ->withCount(['displays' => function($q) use ($sevenDaysAgo) {
                $q->where('last_event_at', '>=', $sevenDaysAgo);
            }])
            ->with(['displays' => function($q) use ($sevenDaysAgo) {
                $q->where('last_event_at', '>=', $sevenDaysAgo)->orderByDesc('last_event_at');
            }])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($user) {
                $user->last_display_activity = $user->displays->max('last_event_at');
                $user->is_paid = $user->hasPro();
                return $user;
            })
            ->values();

        $activeInstancesCount = $activeInstances->count();

        return view('pages.admin', [
            'activeInstances' => $activeInstances,
            'activeUsers' => $activeUsers,
            'activeDisplays' => $activeDisplays,
            'totalDisplays' => $totalDisplays,
            'activeInstancesCount' => $activeInstancesCount,
            'totalInstances' => $totalInstances,
        ]);
    }
} 