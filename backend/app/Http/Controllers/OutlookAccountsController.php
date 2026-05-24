<?php

namespace App\Http\Controllers;

use App\Enums\OutlookBookingMethod;
use App\Enums\PermissionType;
use App\Models\OutlookAccount;
use App\Services\OutlookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

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
    public function callback(): \Illuminate\Http\Response|RedirectResponse
    {
        // Admin consent callback — Microsoft redirects here after a tenant admin
        // approves the app. The admin has no Spacepad session, so we must not
        // require auth. The account ID encoded in the state param is the trust
        // anchor; no auth()->id() check is needed or possible here.
        if (request('admin_consent') === 'True') {
            $state = request('state', '');
            if (str_starts_with($state, 'account:')) {
                $accountId = substr($state, strlen('account:'));
                OutlookAccount::where('id', $accountId)
                    ->update(['booking_method' => OutlookBookingMethod::ADMIN_CONSENT]);
            }

            return response()->view('outlook.admin-consent-granted');
        }

        // Everything below is only reached by the Spacepad user doing their own
        // OAuth flow — they are always logged in at this point.
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (request()->has('error')) {
            $error = request('error', '');
            $errorDescription = request('error_description', '');

            $needsAdminConsent = $error === 'consent_required'
                || $error === 'interaction_required'
                || str_contains($errorDescription, 'AADSTS65001')
                || ($error === 'access_denied' && str_contains(strtolower($errorDescription), 'admin'));

            if ($needsAdminConsent) {
                return redirect()->route('dashboard')->with([
                    'needs_admin_consent' => true,
                    'admin_consent_url' => $this->outlookService->getAdminConsentUrl(),
                ]);
            }

            return redirect()->route('dashboard')->with('error', 'Failed to connect to Microsoft account. Please try again.');
        }

        $authCode = request('code');
        $permissionType = PermissionType::from(session('outlook_permission_type', PermissionType::READ->value));

        // Clear the session value after retrieving it
        session()->forget('outlook_permission_type');

        $outlookAccount = $this->outlookService->authenticateOutlookAccount($authCode, $permissionType);

        return redirect()->route('dashboard')->with('success', 'Microsoft account "'.$outlookAccount->email.'" has been connected successfully.');
    }

    public function setBookingMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'outlook_account_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('outlook_accounts', 'id')->where('user_id', auth()->id()),
            ],
            'booking_method' => ['required', new Enum(OutlookBookingMethod::class)],
        ]);

        $outlookAccount = OutlookAccount::where('id', $request->outlook_account_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($request->booking_method === OutlookBookingMethod::ADMIN_CONSENT->value) {
            // Don't save admin_consent yet — only confirm it after the consent callback
            // returns admin_consent=True. Pass the account ID in state so the callback
            // knows which account to update.
            $consentUrl = $this->outlookService->getAdminConsentUrl($outlookAccount->id);

            return redirect()->route('dashboard')
                ->with('needs_admin_consent', true)
                ->with('admin_consent_url', $consentUrl)
                ->with('info', 'Complete the admin consent step below. The booking method will be saved once your M365 admin approves.');
        }

        $outlookAccount->update([
            'booking_method' => OutlookBookingMethod::from($request->booking_method),
        ]);

        return redirect()->route('dashboard')->with('success', 'Booking method has been set successfully.');
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
