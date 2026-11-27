<?php

namespace App\Http\Controllers;

use App\Enums\PermissionType;
use App\Models\CalDAVAccount;
use App\Rules\SafeCalDavUrl;
use App\Services\CalDAVService;
use App\Services\FunnelTracking;
use App\Support\OnboardingReturn;
use App\Support\PortalShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalDAVAccountsController extends Controller
{
    public function __construct(protected CalDAVService $caldavService) {}

    public function create(Request $request): Response
    {
        OnboardingReturn::remember($request);

        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        $onboarding = $request->boolean('onboarding');

        return Inertia::render('Calendars/CalDav', [
            'shell' => PortalShell::for($request, $workspace, 'calendars'),
            'onboarding' => $onboarding,
            'urls' => [
                'store' => route('caldav-accounts.store'),
                'back' => $onboarding ? route('setup.first-display', ['step' => 'calendar']) : route('calendars.index'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', new SafeCalDavUrl],
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Test connection before creating account
        $connectionTest = $this->caldavService->checkConnection(
            $validated['url'],
            $validated['username'],
            $validated['password']
        );

        if (! $connectionTest['success']) {
            return back()->withErrors([
                'connection' => $connectionTest['message'],
            ])->withInput();
        }

        // Get selected workspace (from session or default to primary)
        $selectedWorkspace = auth()->user()->getSelectedWorkspace();

        if (! $selectedWorkspace) {
            return back()->withErrors(['connection' => 'No workspace found. Please contact support.'])->withInput();
        }

        $workspaceId = $selectedWorkspace->id;

        // Create the CalDAV account
        $account = CalDAVAccount::create([
            'user_id' => auth()->id(),
            'workspace_id' => $workspaceId,
            'name' => parse_url($validated['url'], PHP_URL_HOST),
            'email' => $validated['username'],
            'url' => $validated['url'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'permission_type' => PermissionType::WRITE,
        ]);

        FunnelTracking::calendarConnected($selectedWorkspace, $account->wasRecentlyCreated);

        return OnboardingReturn::redirect('room', 'calendars.index')
            ->with('status', 'CalDAV account has been connected successfully.')
            ->with('success', 'CalDAV account has been connected successfully.');
    }

    public function delete(CalDAVAccount $caldavAccount): RedirectResponse
    {
        $this->authorize('delete', $caldavAccount);

        $caldavAccount->delete();

        return redirect()
            ->route('calendars.index')
            ->with('status', 'CalDAV account has been removed successfully.');
    }
}
