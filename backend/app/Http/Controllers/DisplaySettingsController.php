<?php

namespace App\Http\Controllers;

use App\Helpers\DisplaySettings;
use App\Helpers\DisplaySettingSections;
use App\Models\Display;
use App\Models\DisplaySetting;
use App\Services\ImageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisplaySettingsController extends Controller
{
    public function __construct(
        protected ImageService $imageService
    ) {}

    /**
     * The single configuration screen for a display: behaviour, display, texts, branding and
     * advertisement, each of which independently either follows the linked profile or has its own
     * values. Replaces the old separate settings and customization screens.
     */
    public function configure(Display $display): View|RedirectResponse
    {
        $this->authorize('update', $display);

        if (! $this->hasProAccess($display)) {
            return redirect()->route('dashboard')->with('error', 'Display configuration is only available for Pro users.');
        }

        // No profile list: linking and unlinking live on the Displays tab, which can do it for
        // several displays at once. This screen only reports which profile is followed.
        return view('pages.displays.configure', [
            'display' => $display->load('calendar', 'settings', 'profile.settings'),
            'sections' => DisplaySettingSections::all(),
        ]);
    }

    /**
     * Link (or unlink) the display to a profile so it inherits the profile's settings.
     */
    public function updateProfile(Request $request, Display $display): RedirectResponse
    {
        $this->authorize('update', $display);

        if (! $this->hasProAccess($display)) {
            return redirect()->route('dashboard')->with('error', 'Display configuration is only available for Pro users.');
        }

        $validated = $request->validate([
            'display_profile_id' => [
                'nullable',
                Rule::exists('display_profiles', 'id')
                    ->where('workspace_id', $display->workspace_id),
            ],
        ]);

        $display->update(['display_profile_id' => $validated['display_profile_id'] ?? null]);
        $display->touch();

        return redirect()->route('displays.configure', $display)
            ->with('success', 'Profile link updated successfully.');
    }

    /**
     * Save one section.
     *
     * Only that section's keys are written, which is what makes the per-section model work: editing
     * the branding of one room leaves its behaviour following the profile. The named
     * DisplaySettings setters are used on purpose — several of them carry legacy-key handling and
     * value validation that a generic write would skip.
     */
    public function updateSection(Request $request, Display $display, string $section): RedirectResponse
    {
        $this->authorize('update', $display);

        if (! $this->hasProAccess($display)) {
            return redirect()->route('dashboard')->with('error', 'Display configuration is only available for Pro users.');
        }

        abort_unless(DisplaySettingSections::exists($section), 404);

        if ($section === DisplaySettingSections::ADVERTISEMENT && ! auth()->user()->hasAdvertisementFeature()) {
            abort(403, 'Advertisement feature is not enabled for your account.');
        }

        $request->validate($this->sectionRules($section));

        $updated = match ($section) {
            DisplaySettingSections::BEHAVIOR => $this->saveBehavior($request, $display),
            DisplaySettingSections::DISPLAY => $this->saveDisplay($request, $display),
            DisplaySettingSections::TEXTS => $this->saveTexts($request, $display),
            DisplaySettingSections::BRANDING => $this->saveBranding($request, $display),
            DisplaySettingSections::ADVERTISEMENT => $this->saveAdvertisement($request, $display),
        };

        if (! $updated) {
            return back()->withErrors(['error' => 'Failed to update settings']);
        }

        // Touch the display so the kiosk cache busts and images get a fresh version query string.
        $display->touch();

        return redirect()->route('displays.configure', $display)
            ->with('success', 'Settings updated. Changes may take up to 1 minute to appear on your display.');
    }

    /**
     * Drop this display's own values for one section so it follows its profile again.
     */
    public function resetSection(Display $display, string $section): RedirectResponse
    {
        $this->authorize('update', $display);

        if (! $this->hasProAccess($display)) {
            return redirect()->route('dashboard')->with('error', 'Display configuration is only available for Pro users.');
        }

        abort_unless(DisplaySettingSections::exists($section), 404);

        DisplaySettings::resetSection($display, $section);
        $display->touch();

        return redirect()->route('displays.configure', $display)
            ->with('success', 'This section follows its profile again.');
    }

    /**
     * Remove all of the display's own settings so it fully inherits its linked profile.
     */
    public function resetToProfile(Display $display): RedirectResponse
    {
        $this->authorize('update', $display);

        if (! $this->hasProAccess($display)) {
            return redirect()->route('dashboard')->with('error', 'Display configuration is only available for Pro users.');
        }

        DisplaySetting::where('display_id', $display->id)->delete();
        $display->touch();

        return redirect()->route('displays.configure', $display)
            ->with('success', 'Display settings reset. This display now follows its profile.');
    }

    /**
     * Assign (or clear) a profile for several displays at once from the displays overview.
     *
     * Assigning means "make these rooms follow this profile", so the selected displays lose their own
     * values — otherwise the profile would appear to do nothing on exactly the displays that were
     * customised before.
     */
    public function bulkAssignProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $workspace = $user->getSelectedWorkspace();

        if (! $workspace || ! $user->hasProForWorkspace($workspace)) {
            return redirect()->route('dashboard')->with('error', 'Profiles are only available for Pro users.');
        }

        $validated = $request->validate([
            'display_ids' => 'required|array|min:1',
            'display_ids.*' => 'string',
            'display_profile_id' => [
                'nullable',
                Rule::exists('display_profiles', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        // Never trust the posted ids: restrict them to displays of the selected workspace.
        $displays = Display::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['display_ids'])
            ->get();

        if ($displays->isEmpty()) {
            return redirect()->route('dashboard', ['tab' => 'displays'])
                ->with('error', 'No displays were updated.');
        }

        $profileId = $validated['display_profile_id'] ?? null;

        foreach ($displays as $display) {
            $this->authorize('update', $display);
        }

        // Clearing own settings only makes sense when linking; unlinking keeps the values so a room
        // does not silently fall back to defaults.
        if ($profileId !== null) {
            DisplaySetting::whereIn('display_id', $displays->pluck('id'))->delete();
        }

        // Eloquent's mass update maintains updated_at, so the kiosk cache busts without an extra touch.
        Display::whereIn('id', $displays->pluck('id'))->update(['display_profile_id' => $profileId]);

        $count = $displays->count();
        $message = $profileId === null
            ? "{$count} ".($count === 1 ? 'display' : 'displays').' unlinked from their profile.'
            : "{$count} ".($count === 1 ? 'display' : 'displays').' now follow the selected profile.';

        return redirect()->route('dashboard', ['tab' => 'displays'])->with('success', $message);
    }

    /**
     * Serve display images (logo, background or advertisement)
     */
    public function serveImage(Display $display, string $type)
    {
        // Use the policy to check access for both User and Device models
        $this->authorize('view', $display);

        return $this->imageService->serveImage($display, $type);
    }

    private function hasProAccess(Display $display): bool
    {
        return (bool) $display->workspace_id && auth()->user()->hasProForWorkspace($display->workspace);
    }

    /**
     * Validation rules per section, mirroring the fields the section renders.
     *
     * @return array<string, mixed>
     */
    private function sectionRules(string $section): array
    {
        return match ($section) {
            DisplaySettingSections::BEHAVIOR => [
                'check_in_enabled' => 'boolean',
                'check_in_minutes' => 'nullable|integer|min:1|max:60',
                'check_in_grace_period' => 'nullable|integer|min:1|max:30',
                'booking_enabled' => 'boolean',
                'allow_future_bookings' => 'boolean',
                'extend_enabled' => 'boolean',
                'cancel_permission' => 'nullable|in:all,tablet_only,none',
                'hide_admin_actions' => 'boolean',
            ],
            DisplaySettingSections::DISPLAY => [
                'view_schedule' => 'boolean',
                'timeline_widget_mode' => 'nullable|in:none,side_panel,inline,full_panel',
                'border_thickness' => 'nullable|in:small,medium,large',
                'show_organizer' => 'boolean',
                'show_meeting_title' => 'boolean',
            ],
            DisplaySettingSections::TEXTS => [
                'text_available' => 'nullable|string|max:64',
                'text_transitioning' => 'nullable|string|max:64',
                'text_reserved' => 'nullable|string|max:64',
                'text_checkin' => 'nullable|string|max:64',
            ],
            DisplaySettingSections::BRANDING => [
                'font_family' => 'nullable|string|in:Inter,Roboto,Open Sans,Lato,Poppins,Montserrat',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'remove_logo' => 'boolean',
                'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'remove_background_image' => 'boolean',
                'default_background' => 'nullable|string|in:default_1,default_2,default_3,default_4,default_5,default_6,default_7,default_8',
            ],
            DisplaySettingSections::ADVERTISEMENT => [
                'advertisement_enabled' => 'boolean',
                'advertisement_interval' => 'nullable|integer|min:1|max:60',
                'advertisement_duration' => 'nullable|integer|min:5|max:300',
                'advertisement_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096',
                'remove_advertisement_image' => 'boolean',
            ],
            default => [],
        };
    }

    private function saveBehavior(Request $request, Display $display): bool
    {
        $updated = DisplaySettings::setCheckInEnabled($display, $request->boolean('check_in_enabled'));
        $updated = $updated && DisplaySettings::setBookingEnabled($display, $request->boolean('booking_enabled'));
        $updated = $updated && DisplaySettings::setFutureBookingEnabled($display, $request->boolean('allow_future_bookings'));
        $updated = $updated && DisplaySettings::setExtendEnabled($display, $request->boolean('extend_enabled'));
        $updated = $updated && DisplaySettings::setAdminActionsHidden($display, $request->boolean('hide_admin_actions'));
        $updated = $updated && DisplaySettings::setCancelPermission($display, $request->input('cancel_permission', 'all'));

        // Timing is only meaningful with check-in on, but store it either way so toggling check-in
        // back on keeps the values the user configured.
        $updated = $updated && DisplaySettings::setCheckInMinutes($display, (int) $request->input('check_in_minutes', 15));
        $updated = $updated && DisplaySettings::setCheckInGracePeriod($display, (int) $request->input('check_in_grace_period', 5));

        return $updated;
    }

    private function saveDisplay(Request $request, Display $display): bool
    {
        $updated = DisplaySettings::setTimelineWidgetMode($display, $request->input('timeline_widget_mode', 'none'));
        $updated = $updated && DisplaySettings::setCalendarEnabled($display, $request->boolean('view_schedule'));
        $updated = $updated && DisplaySettings::setShowOrganizerEnabled($display, $request->boolean('show_organizer'));
        $updated = $updated && DisplaySettings::setShowMeetingTitle($display, $request->boolean('show_meeting_title'));
        $updated = $updated && DisplaySettings::setBorderThickness($display, $request->input('border_thickness', 'medium'));

        return $updated;
    }

    /**
     * An empty text field means "no custom text here": the key is removed, so the display falls back
     * to its profile's text and, failing that, to the built-in default.
     */
    private function saveTexts(Request $request, Display $display): bool
    {
        $setters = [
            'text_available' => 'setAvailableText',
            'text_transitioning' => 'setTransitioningText',
            'text_reserved' => 'setReservedText',
            'text_checkin' => 'setCheckInText',
        ];

        $updated = true;

        foreach ($setters as $key => $setter) {
            if (filled($request->input($key))) {
                $updated = $updated && DisplaySettings::$setter($display, $request->input($key));
            } else {
                DisplaySettings::deleteSetting($display, $key);
            }
        }

        return $updated;
    }

    private function saveBranding(Request $request, Display $display): bool
    {
        $updated = true;

        if ($request->has('font_family')) {
            $updated = DisplaySettings::setFontFamily($display, $request->input('font_family'));
        }

        // Logo
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

        // Background: removal, custom upload or one of the bundled defaults
        if ($request->boolean('remove_background_image')) {
            $this->imageService->removeBackgroundImageFile($display);
            $updated = $updated && DisplaySettings::removeBackgroundImage($display);
        } elseif ($request->hasFile('background_image')) {
            $backgroundPath = $this->imageService->storeBackgroundImageFile($request->file('background_image'), $display);
            if ($backgroundPath) {
                $this->imageService->removeBackgroundImageFile($display); // Remove old background if exists
                $updated = $updated && DisplaySettings::setBackgroundImage($display, $backgroundPath);
            } else {
                $updated = false;
            }
        } elseif ($request->filled('default_background')) {
            $defaultKey = $request->input('default_background');
            if (isset(ImageService::DEFAULT_BACKGROUNDS[$defaultKey])) {
                // Drop a previously uploaded custom background before switching to a default
                $currentBackground = DisplaySettings::getBackgroundImage($display);
                if ($currentBackground && ! isset(ImageService::DEFAULT_BACKGROUNDS[$currentBackground])) {
                    $this->imageService->removeBackgroundImageFile($display);
                }
                $updated = $updated && DisplaySettings::setBackgroundImage($display, $defaultKey);
            }
        }

        return $updated;
    }

    private function saveAdvertisement(Request $request, Display $display): bool
    {
        $updated = DisplaySettings::setAdvertisementEnabled($display, $request->boolean('advertisement_enabled'));
        $updated = $updated && DisplaySettings::setAdvertisementInterval($display, (int) $request->input('advertisement_interval', 5));
        $updated = $updated && DisplaySettings::setAdvertisementDuration($display, (int) $request->input('advertisement_duration', 15));

        if ($request->boolean('remove_advertisement_image')) {
            $this->imageService->removeAdvertisementFile($display);
            $updated = $updated && DisplaySettings::removeAdvertisementImage($display);
        } elseif ($request->hasFile('advertisement_image')) {
            $adPath = $this->imageService->storeAdvertisementFile($request->file('advertisement_image'), $display);
            if ($adPath) {
                $this->imageService->removeAdvertisementFile($display);
                $updated = $updated && DisplaySettings::setAdvertisementImage($display, $adPath);
            } else {
                $updated = false;
            }
        }

        return $updated;
    }
}
