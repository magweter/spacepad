<?php

namespace App\Http\Controllers;

use App\Helpers\DisplaySettings;
use App\Models\Display;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;

class DisplaySettingsController extends Controller
{
    public function index(Display $display): View
    {
        $this->authorize('update', $display);

        // Check if user has Pro access
        if (!auth()->user()->hasPro()) {
            return redirect()->route('dashboard')->with('error', 'Display settings are only available for Pro users.');
        }

        return view('pages.displays.settings', [
            'display' => $display->load('calendar')
        ]);
    }

    public function update(Request $request, Display $display): RedirectResponse
    {
        $this->authorize('update', $display);

        // Check if user has Pro access
        if (!auth()->user()->hasPro()) {
            return redirect()->route('dashboard')->with('error', 'Display settings are only available for Pro users.');
        }

        $request->validate([
            'check_in_enabled' => 'boolean',
            'booking_enabled' => 'boolean',
        ]);

        $updated = true;

        if ($request->has('check_in_enabled')) {
            $updated = $updated && DisplaySettings::setCheckInEnabled(
                $display, 
                $request->boolean('check_in_enabled')
            );
        }

        if ($request->has('booking_enabled')) {
            $updated = $updated && DisplaySettings::setBookingEnabled(
                $display, 
                $request->boolean('booking_enabled')
            );
        }

        if (!$updated) {
            return back()->withErrors(['error' => 'Failed to update settings']);
        }

        return redirect()->route('dashboard')->with('success', 'Display settings updated successfully');
    }
} 