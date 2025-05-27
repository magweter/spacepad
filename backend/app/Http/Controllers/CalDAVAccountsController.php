<?php

namespace App\Http\Controllers;

use App\Models\CalDAVAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\CalDAVService;

class CalDAVAccountsController extends Controller
{
    public function __construct(protected CalDAVService $caldavService)
    {
    }

    public function create(): View
    {
        return view('pages.caldav-accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Test connection before creating account
        $connectionTest = $this->caldavService->checkConnection(
            $validated['url'],
            $validated['username'],
            $validated['password']
        );

        if (!$connectionTest['success']) {
            return back()->withErrors([
                'connection' => $connectionTest['message']
            ])->withInput();
        }

        // Create the CalDAV account
        $account = CalDAVAccount::create([
            'user_id' => auth()->id(),
            'name' => parse_url($validated['url'], PHP_URL_HOST),
            'email' => $validated['username'],
            'url' => $validated['url'],
            'username' => $validated['username'],
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'CalDAV account has been connected successfully.');
    }

    public function delete(CalDAVAccount $caldavAccount): RedirectResponse
    {
        $this->authorize('delete', $caldavAccount);

        $caldavAccount->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'CalDAV account has been removed successfully.');
    }
} 