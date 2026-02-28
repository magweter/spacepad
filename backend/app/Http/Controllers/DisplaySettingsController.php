<?php

namespace App\Http\Controllers;

use App\Helpers\DisplaySettings;
use App\Models\Display;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use App\Http\Requests\UpdateDisplayCustomizationRequest;

class DisplaySettingsController extends Controller
{
    public function __construct(
        protected ImageService $imageService
    ) {
    }
    public function index(Display $display): View
    {
        $this->authorize('update', $display);

        // Check if user has Pro access (workspace-aware)
        if (!auth()->user()->hasProForCurrentWorkspace()) {
            return redirect()->route('dashboard')->with('error', 'Display settings are only available for Pro users.');
        }

        return view('pages.displays.settings', [
            'display' => $display->load('calendar')
        ]);
    }

    public function update(Request $request, Display $display): RedirectResponse
    {
        $this->authorize('update', $display);

        // Check if user has Pro access (workspace-aware)
        if (!auth()->user()->hasProForCurrentWorkspace()) {
            return redirect()->route('dashboard')->with('error', 'Display settings are only available for Pro users.');
        }

        $request->validate([
            'check_in_enabled' => 'boolean',
            'booking_enabled' => 'boolean',
            'calendar_enabled' => 'boolean',
            'hide_admin_actions' => 'boolean',
            'check_in_minutes' => 'nullable|integer|min:1|max:60',
            'check_in_grace_period' => 'nullable|integer|min:1|max:30',
            'cancel_permission' => 'nullable|in:all,tablet_only,none',
            'border_thickness' => 'nullable|in:small,medium,large',
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

        $updated = $updated && DisplaySettings::setAdminActionsHidden(
            $display,
            $request->boolean('hide_admin_actions')
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

        // Handle cancel permission
        if ($request->has('cancel_permission')) {
            $updated = $updated && DisplaySettings::setCancelPermission(
                $display,
                $request->input('cancel_permission')
            );
        }

        // Handle border thickness
        if ($request->has('border_thickness')) {
            $updated = $updated && DisplaySettings::setBorderThickness(
                $display,
                $request->input('border_thickness')
            );
        }

        if (!$updated) {
            return back()->withErrors(['error' => 'Failed to update settings']);
        }

        // Touch the display to update its updated_at timestamp
        $display->touch();

        return redirect()->route('dashboard')->with('success', 'Display settings updated successfully');
    }

    public function customization(Display $display): View
    {
        $this->authorize('update', $display);

        if (!auth()->user()->hasProForCurrentWorkspace()) {
            return redirect()->route('dashboard')->with('error', 'Display customization is only available for Pro users.');
        }

        return view('pages.displays.customization', [
            'display' => $display->load('calendar')
        ]);
    }

    public function updateCustomization(UpdateDisplayCustomizationRequest $request, Display $display): RedirectResponse
    {
        $this->authorize('update', $display);

        if (!auth()->user()->hasProForCurrentWorkspace()) {
            return redirect()->route('dashboard')->with('error', 'Display customization is only available for Pro users.');
        }

        $updated = true;
        // Handle text_available
        if (filled($request->input('text_available'))) {
            $updated = $updated && DisplaySettings::setAvailableText($display, $request->input('text_available'));
        } else {
            DisplaySettings::deleteSetting($display, 'text_available');
        }

        // Handle text_transitioning
        if (filled($request->input('text_transitioning'))) {
            $updated = $updated && DisplaySettings::setTransitioningText($display, $request->input('text_transitioning'));
        } else {
            DisplaySettings::deleteSetting($display, 'text_transitioning');
        }

        // Handle text_reserved
        if (filled($request->input('text_reserved'))) {
            $updated = $updated && DisplaySettings::setReservedText($display, $request->input('text_reserved'));
        } else {
            DisplaySettings::deleteSetting($display, 'text_reserved');
        }

        // Handle text_checkin
        if (filled($request->input('text_checkin'))) {
            $updated = $updated && DisplaySettings::setCheckInText($display, $request->input('text_checkin'));
        } else {
            DisplaySettings::deleteSetting($display, 'text_checkin');
        }

        // Handle show_meeting_title (always set, default to false if not present)
        $updated = $updated && DisplaySettings::setShowMeetingTitle($display, $request->boolean('show_meeting_title'));

        // Handle font_family
        if ($request->has('font_family')) {
            $updated = $updated && DisplaySettings::setFontFamily($display, $request->input('font_family'));
        }

        // Handle logo upload/removal
        if ($request->boolean('remove_logo')) {
            $this->imageService->removeLogoFile($display);
            $updated = $updated && DisplaySettings::removeLogo($display);
        } elseif ($request->hasFile('logo')) {
            $logoPath = $this->imageService->storeLogoFile($request->file('logo'), $display);
            if ($logoPath) {
                $this->imageService->removeLogoFile($display); // Remove old logo if exists
                $updated = $updated && DisplaySettings::setLogo($display, $logoPath);
            } else {
                $updated = false;
            }
        }

        // Handle background image upload/removal/default selection
        if ($request->boolean('remove_background_image')) {
            $this->imageService->removeBackgroundImageFile($display);
            $updated = $updated && DisplaySettings::removeBackgroundImage($display);
        } elseif ($request->hasFile('background_image')) {
            // Custom uploaded background
            $backgroundPath = $this->imageService->storeBackgroundImageFile($request->file('background_image'), $display);
            if ($backgroundPath) {
                $this->imageService->removeBackgroundImageFile($display); // Remove old background if exists
                $updated = $updated && DisplaySettings::setBackgroundImage($display, $backgroundPath);
            } else {
                $updated = false;
            }
        } elseif ($request->filled('default_background')) {
            // Default background selected
            $defaultKey = $request->input('default_background');
            if (isset(\App\Services\ImageService::DEFAULT_BACKGROUNDS[$defaultKey])) {
                // Remove old custom uploaded background if exists
                $currentBackground = DisplaySettings::getBackgroundImage($display);
                if ($currentBackground && !isset(\App\Services\ImageService::DEFAULT_BACKGROUNDS[$currentBackground])) {
                    $this->imageService->removeBackgroundImageFile($display);
                }
                // Store the default background key
                $updated = $updated && DisplaySettings::setBackgroundImage($display, $defaultKey);
            }
        }

        if (!$updated) {
            return back()->withErrors(['error' => 'Failed to update customization settings']);
        }

        // Touch the display to update its updated_at timestamp for cache busting
        $display->touch();

        return redirect()->route('displays.customization', $display)->with('success', 'Customization settings updated successfully. Changes may take up to 1 minute to appear on your display.');
    }


    /**
     * Serve display images (logo or background)
     */
    public function serveImage(Display $display, string $type)
    {
        // Use the policy to check access for both User and Device models
        $this->authorize('view', $display);
        
        return $this->imageService->serveImage($display, $type);
    }
}
