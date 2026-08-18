<?php

namespace App\Http\Controllers;

use App\Helpers\DisplaySettings;
use App\Helpers\DisplaySettingSections;
use App\Helpers\ProfileSettings;
use App\Http\Requests\DisplayProfileRequest;
use App\Models\DisplayProfile;
use App\Services\ImageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProfilesController extends Controller
{
    public function __construct(
        protected ImageService $imageService
    ) {}

    /**
     * The profile list lives in the dashboard as a tab, next to Displays and Boards, so this only
     * keeps the /profiles URL working.
     */
    public function index(): RedirectResponse
    {
        if (! auth()->user()->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        return redirect()->route('dashboard', ['tab' => 'profiles']);
    }

    /**
     * Show the form for creating a new profile.
     */
    public function create(): View
    {
        $user = auth()->user();

        if (! $user->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        $this->authorize('create', DisplayProfile::class);

        $workspace = $user->getSelectedWorkspace();

        if (! $workspace) {
            abort(404, 'No workspace found');
        }

        return view('pages.profiles.form', [
            'profile' => null,
            'linkedDisplays' => collect(),
            'settings' => [],
            'workspace' => $workspace,
        ]);
    }

    /**
     * Store a newly created profile.
     */
    public function store(DisplayProfileRequest $request): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        $this->authorize('create', DisplayProfile::class);

        $workspace = $user->getSelectedWorkspace();

        if (! $workspace) {
            abort(404, 'No workspace found');
        }

        $profile = DisplayProfile::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'name' => $request->validated('name'),
        ]);

        $this->applySettings($profile, $request);

        return redirect()->route('profiles.index')
            ->with('success', 'Profile created successfully.');
    }

    /**
     * Show the form for editing the specified profile.
     */
    public function edit(DisplayProfile $profile): View
    {
        $user = auth()->user();

        if (! $user->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        $this->authorize('update', $profile);

        return view('pages.profiles.form', [
            'profile' => $profile,
            'linkedDisplays' => $profile->displays()->orderBy('name')->get(),
            'settings' => ProfileSettings::all($profile),
            'workspace' => $profile->workspace,
        ]);
    }

    /**
     * Update the specified profile.
     */
    public function update(DisplayProfileRequest $request, DisplayProfile $profile): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        $this->authorize('update', $profile);

        $profile->update(['name' => $request->validated('name')]);

        $this->applySettings($profile, $request);

        return redirect()->route('profiles.index')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Remove the specified profile.
     *
     * The profile's values are copied onto the linked displays first, so rooms keep behaving exactly
     * as they did. Without that copy the displays would fall back to the built-in defaults the moment
     * the foreign key is nulled — a silent change on every wall.
     */
    public function destroy(DisplayProfile $profile): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        $this->authorize('delete', $profile);

        $this->copySettingsToLinkedDisplays($profile);

        $profile->delete();

        return redirect()->route('profiles.index')
            ->with('success', 'Profile deleted. Linked displays kept its settings as their own.');
    }

    /**
     * Handle the profile's uploaded images: logo, background (upload or bundled default) and the
     * advertisement image. Displays that follow this profile resolve to these paths automatically.
     */
    private function applyImages(DisplayProfile $profile, DisplayProfileRequest $request): void
    {
        // Logo
        if ($request->boolean('remove_logo')) {
            $this->imageService->removeProfileImageFile($profile, 'logo');
            ProfileSettings::delete($profile, 'logo');
        } elseif ($request->hasFile('logo')) {
            $path = $this->imageService->storeProfileImageFile($request->file('logo'), $profile, 'logo');
            if ($path) {
                $this->imageService->removeProfileImageFile($profile, 'logo');
                ProfileSettings::set($profile, 'logo', $path, 'string');
            }
        }

        // Background: removal, custom upload or one of the bundled defaults
        if ($request->boolean('remove_background_image')) {
            $this->imageService->removeProfileImageFile($profile, 'background_image');
            ProfileSettings::delete($profile, 'background_image');
        } elseif ($request->hasFile('background_image')) {
            $path = $this->imageService->storeProfileImageFile($request->file('background_image'), $profile, 'background');
            if ($path) {
                $this->imageService->removeProfileImageFile($profile, 'background_image');
                ProfileSettings::set($profile, 'background_image', $path, 'string');
            }
        } elseif ($request->filled('default_background')) {
            $key = $request->input('default_background');
            if (isset(ImageService::DEFAULT_BACKGROUNDS[$key])) {
                $this->imageService->removeProfileImageFile($profile, 'background_image');
                ProfileSettings::set($profile, 'background_image', $key, 'string');
            }
        }

        // Advertisement image
        if ($request->boolean('remove_advertisement_image')) {
            $this->imageService->removeProfileImageFile($profile, 'advertisement_image');
            ProfileSettings::delete($profile, 'advertisement_image');
        } elseif ($request->hasFile('advertisement_image')) {
            if (! auth()->user()->hasAdvertisementFeature()) {
                abort(403, 'Advertisement feature is not enabled for your account.');
            }
            $path = $this->imageService->storeProfileImageFile($request->file('advertisement_image'), $profile, 'advertisement');
            if ($path) {
                $this->imageService->removeProfileImageFile($profile, 'advertisement_image');
                ProfileSettings::set($profile, 'advertisement_image', $path, 'string');
            }
        }
    }

    /**
     * Serve a profile image for the previews on the profile form.
     */
    public function serveImage(DisplayProfile $profile, string $type)
    {
        $this->authorize('update', $profile);

        return $this->imageService->serveProfileImage($profile, $type);
    }

    /**
     * Write the profile's settings onto each linked display, leaving values the display already
     * overrides untouched.
     */
    private function copySettingsToLinkedDisplays(DisplayProfile $profile): void
    {
        $profileSettings = ProfileSettings::all($profile);

        if ($profileSettings === []) {
            return;
        }

        $types = DisplaySettingSections::allProfileKeys();

        foreach ($profile->displays()->with('settings')->get() as $display) {
            $ownKeys = $display->settings->pluck('key')->all();

            foreach ($profileSettings as $key => $value) {
                // An existing override is the display's own choice; never overwrite it.
                if (in_array($key, $ownKeys, true)) {
                    continue;
                }

                DisplaySettings::setSetting($display, $key, $value, $types[$key] ?? 'string');
            }
        }
    }

    /**
     * Persist the profile's settings from the validated request.
     */
    private function applySettings(DisplayProfile $profile, DisplayProfileRequest $request): void
    {
        // Booleans — always stored so the profile can override non-default values.
        $booleans = [
            'check_in_enabled', 'booking_enabled', 'hide_admin_actions', 'view_schedule',
            'allow_future_bookings', 'extend_enabled', 'show_organizer',
            'show_meeting_title', 'show_room_name', 'advertisement_enabled',
        ];
        foreach ($booleans as $key) {
            ProfileSettings::set($profile, $key, $request->boolean($key), 'boolean');
        }

        // Enumerated / string selects
        ProfileSettings::set($profile, 'timeline_widget_mode', $request->input('timeline_widget_mode', 'none'), 'string');
        ProfileSettings::set($profile, 'cancel_permission', $request->input('cancel_permission', 'all'), 'string');
        ProfileSettings::set($profile, 'border_thickness', $request->input('border_thickness', 'medium'), 'string');
        ProfileSettings::set($profile, 'font_family', $request->input('font_family', 'Inter'), 'string');

        // Integers
        ProfileSettings::set($profile, 'check_in_minutes', (int) $request->input('check_in_minutes', 15), 'integer');
        ProfileSettings::set($profile, 'check_in_grace_period', (int) $request->input('check_in_grace_period', 5), 'integer');

        // Text overrides — empty clears the key so displays fall back to their default.
        foreach (['text_available', 'text_transitioning', 'text_reserved', 'text_checkin'] as $key) {
            if (filled($request->input($key))) {
                ProfileSettings::set($profile, $key, $request->input($key), 'string');
            } else {
                ProfileSettings::delete($profile, $key);
            }
        }

        $this->applyImages($profile, $request);

        // Advertisement timing is gated behind the advertisement feature (mirrors display settings).
        if (auth()->user()->hasAdvertisementFeature()) {
            if ($request->filled('advertisement_interval')) {
                ProfileSettings::set($profile, 'advertisement_interval', (int) $request->input('advertisement_interval'), 'integer');
            }
            if ($request->filled('advertisement_duration')) {
                ProfileSettings::set($profile, 'advertisement_duration', (int) $request->input('advertisement_duration'), 'integer');
            }
        }
    }
}
