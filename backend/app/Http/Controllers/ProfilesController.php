<?php

namespace App\Http\Controllers;

use App\Enums\DisplayStatus;
use App\Helpers\ProfileSettings;
use App\Http\Requests\DisplayProfileRequest;
use App\Models\Display;
use App\Models\DisplayProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProfilesController extends Controller
{
    /**
     * List the display profiles for the current workspace.
     */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        $workspace = $user->getSelectedWorkspace();

        if (! $workspace) {
            abort(404, 'No workspace found');
        }

        $profiles = DisplayProfile::where('workspace_id', $workspace->id)
            ->withCount('displays')
            ->with('user')
            ->orderBy('name')
            ->get();

        return view('pages.profiles.index', [
            'profiles' => $profiles,
            'workspace' => $workspace,
        ]);
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
            'displays' => $this->workspaceDisplays($workspace->id),
            'linkedDisplayIds' => [],
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
        $this->syncDisplays($profile, $request->input('display_ids', []), $workspace->id);

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
            'displays' => $this->workspaceDisplays($profile->workspace_id),
            'linkedDisplayIds' => $profile->displays()->pluck('id')->toArray(),
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
        $this->syncDisplays($profile, $request->input('display_ids', []), $profile->workspace_id);

        return redirect()->route('profiles.index')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Remove the specified profile. Linked displays are automatically detached
     * (display_profile_id is set to null via the foreign key constraint).
     */
    public function destroy(DisplayProfile $profile): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasProForCurrentWorkspace()) {
            abort(403, 'Profiles is a Pro feature. Please upgrade to access this feature.');
        }

        $this->authorize('delete', $profile);

        $profile->delete();

        return redirect()->route('profiles.index')
            ->with('success', 'Profile deleted successfully.');
    }

    /**
     * Active/ready displays in the workspace, used for the link selection.
     */
    private function workspaceDisplays(string $workspaceId)
    {
        return Display::where('workspace_id', $workspaceId)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->orderBy('name')
            ->get();
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
            'show_meeting_title', 'advertisement_enabled',
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

    /**
     * Link the given displays to this profile and detach any previously-linked
     * displays that are no longer selected. Scoped to the workspace.
     *
     * @param  array<int, string>  $displayIds
     */
    private function syncDisplays(DisplayProfile $profile, array $displayIds, string $workspaceId): void
    {
        $validIds = Display::where('workspace_id', $workspaceId)
            ->whereIn('id', $displayIds)
            ->pluck('id')
            ->toArray();

        if (! empty($validIds)) {
            Display::whereIn('id', $validIds)->update(['display_profile_id' => $profile->id]);
        }

        // Detach displays currently linked to this profile that were not selected.
        Display::where('display_profile_id', $profile->id)
            ->whereNotIn('id', $validIds)
            ->update(['display_profile_id' => null]);
    }
}
