<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccount;
use App\Services\GoogleService;
use Google\Service\Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoogleAccountsController extends Controller
{
    protected GoogleService $googleService;

    public function __construct(GoogleService $googleService)
    {
        $this->googleService = $googleService;
    }

    public function auth(): RedirectResponse
    {
        return redirect($this->googleService->getAuthUrl());
    }

    /**
     * @throws \Exception
     */
    public function callback(): RedirectResponse
    {
        if (request()->has('error')) {
            return redirect()->route('dashboard')->with('error', 'Failed to connect to Google. Please try again.');
        }

        $authCode = request('code');
        $this->googleService->authenticateGoogleAccount($authCode);

        return redirect()->route('dashboard');
    }

    public function delete(GoogleAccount $googleAccount): RedirectResponse
    {
        $this->authorize('delete', $googleAccount);

        if ($googleAccount->calendars()->exists()) {
            return redirect()->route('dashboard')->with('error', 'Cannot disconnect this account because it is used by one or more displays.');
        }

        $googleAccount->delete();

        return redirect()->route('dashboard')->with('status', 'Google account has been removed successfully.');
    }
}
