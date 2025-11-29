<?php

namespace App\Http\Controllers;

use App\Enums\PermissionType;
use App\Models\GoogleAccount;
use App\Services\GoogleService;
use Google\Service\Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class GoogleAccountsController extends Controller
{
    protected GoogleService $googleService;

    public function __construct(GoogleService $googleService)
    {
        $this->googleService = $googleService;
    }

    public function auth(Request $request): RedirectResponse
    {
        $request->validate([
            'permission_type' => ['required', new Enum(PermissionType::class)],
        ]);

        // Store permission type in session before redirecting to OAuth
        session(['google_permission_type' => $request->permission_type]);

        $permissionType = PermissionType::from($request->permission_type);
        return redirect($this->googleService->getAuthUrl($permissionType));
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
        $permissionType = PermissionType::from(session('google_permission_type', PermissionType::READ->value));

        // Clear the session value after retrieving it
        session()->forget('google_permission_type');

        $this->googleService->authenticateGoogleAccount($authCode, $permissionType);

        return redirect()->route('dashboard');
    }

    public function delete(GoogleAccount $googleAccount): RedirectResponse
    {
        if ($googleAccount->calendars()->exists()) {
            return redirect()->route('dashboard')->with('error', 'Cannot disconnect this account because it is used by one or more displays.');
        }

        $googleAccount->delete();

        return redirect()->route('dashboard')->with('status', 'Google account has been removed successfully.');
    }
}
