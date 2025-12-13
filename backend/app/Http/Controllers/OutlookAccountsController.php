<?php

namespace App\Http\Controllers;

use App\Enums\PermissionType;
use App\Models\OutlookAccount;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class OutlookAccountsController extends Controller
{
    protected OutlookService $outlookService;

    public function __construct(OutlookService $outlookService)
    {
        $this->outlookService = $outlookService;
    }

    public function auth(Request $request): RedirectResponse
    {
        $request->validate([
            'permission_type' => ['required', new Enum(PermissionType::class)],
        ]);

        // Store permission type in session before redirecting to OAuth
        session(['outlook_permission_type' => $request->permission_type]);

        $permissionType = PermissionType::from($request->permission_type);
        return redirect($this->outlookService->getAuthUrl($permissionType));
    }

    /**
     * @throws \Exception
     */
    public function callback(): RedirectResponse
    {
        if (request()->has('error')) {
            return redirect()->route('dashboard')->with('error', 'Failed to connect to Outlook. Please try again.');
        }

        $authCode = request('code');
        $permissionType = PermissionType::from(session('outlook_permission_type', PermissionType::READ->value));

        // Clear the session value after retrieving it
        session()->forget('outlook_permission_type');

        $outlookAccount = $this->outlookService->authenticateOutlookAccount($authCode, $permissionType);

        return redirect()->route('dashboard')->with('success', 'Microsoft account "' . $outlookAccount->email . '" has been connected successfully.');
    }

    public function delete(OutlookAccount $outlookAccount): RedirectResponse
    {
        if ($outlookAccount->calendars()->exists()) {
            return redirect()->route('dashboard')->with('error', 'Cannot disconnect this account because it is used by one or more displays.');
        }

        $outlookAccount->delete();

        return redirect()->route('dashboard')->with('status', 'Outlook account has been removed successfully.');
    }
}
