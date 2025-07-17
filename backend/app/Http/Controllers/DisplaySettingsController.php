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
            'calendar_enabled' => 'boolean',
            'check_in_minutes' => 'nullable|integer|min:1|max:60',
            'check_in_grace_period' => 'nullable|integer|min:1|max:30',
        ]);

        $updated = true;

        $updated = $updated && DisplaySettings::setCheckInEnabled(
            $display,
            $request->boolean('check_in_enabled')
        );

        $updated = $updated && DisplaySettings::setBookingEnabled(
            $display,
            $request->boolean('booking_enabled')
        );

        $updated = $updated && DisplaySettings::setCalendarEnabled(
            $display,
            $request->boolean('calendar_enabled')
        );

        // Only allow updating grace period if check-in is enabled (either in request or already enabled)
        $checkInEnabled = $request->has('check_in_enabled')
            ? $request->boolean('check_in_enabled')
            : $display->isCheckInEnabled();
        if ($checkInEnabled && $request->has('check_in_grace_period')) {
            $updated = $updated && DisplaySettings::setCheckInGracePeriod(
                $display,
                (int) $request->input('check_in_grace_period')
            );
        }

        if ($checkInEnabled && $request->has('check_in_minutes')) {
            $updated = $updated && DisplaySettings::setCheckInMinutes(
                $display,
                (int) $request->input('check_in_minutes')
            );
        }

        if (!$updated) {
            return back()->withErrors(['error' => 'Failed to update settings']);
        }

        return redirect()->route('dashboard')->with('success', 'Display settings updated successfully');
    }
}
