<?php

namespace App\Http\Controllers;

use App\Enums\OutlookBookingMethod;
use App\Enums\PermissionType;
use App\Http\Controllers\Concerns\VerifiesOAuthState;
use App\Models\OutlookAccount;
use App\Services\FunnelTracking;
use App\Services\OutlookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

class OutlookAccountsController extends Controller
{
    use VerifiesOAuthState;

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

        // Bind the flow to this session. The callback only exchanges a code that comes back
        // with the same state, so a code captured by someone else cannot be planted here.
        $state = Str::random(40);
        session(['outlook_oauth_state' => $state]);

        return redirect($this->outlookService->getAuthUrl($permissionType, $state));
    }

    /**
     * @throws \Exception
     */
    public function callback(): Response|RedirectResponse
    {
        // Log every callback hit so we can diagnose cases where the admin-consent
        // branch is unexpectedly skipped (e.g. Microsoft sending a different
        // admin_consent value/casing, an error param, or a missing state).
        // NOTE: the OAuth "code" is a secret, so we only log its presence, never its value.
        logger()->info('Outlook callback received', [
            'param_keys' => array_keys(request()->query()),
            'admin_consent' => request('admin_consent'),
            'has_state' => request()->filled('state'),
            'tenant' => request('tenant'),
            'has_code' => request()->has('code'),
            'error' => request('error'),
            'error_description' => request('error_description'),
            'authenticated' => auth()->check(),
        ]);

        // Admin consent callback — Microsoft redirects here after a tenant admin
        // approves the app. The admin has no Spacepad session, so we must not
        // require auth. The account ID encoded in the state param is the trust
        // anchor; no auth()->id() check is needed or possible here.
        // Accept any truthy casing ("True" / "true" / "1") to be robust to whatever
        // Microsoft actually sends back.
        $adminConsent = request('admin_consent');
        $isAdminConsent = $adminConsent !== null
            && in_array(strtolower((string) $adminConsent), ['true', '1'], true);

        if ($isAdminConsent) {
            return $this->handleAdminConsent();
        }

        // Everything below is only reached by the Spacepad user doing their own
        // OAuth flow — they are always logged in at this point.
        if (! auth()->check()) {
            logger()->warning('Outlook callback: not an admin-consent callback and no authenticated session — redirecting to login', [
                'param_keys' => array_keys(request()->query()),
                'admin_consent' => request('admin_consent'),
                'error' => request('error'),
            ]);

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

        if (! $this->stateMatchesSession('outlook_oauth_state')) {
            session()->forget('outlook_permission_type');

            return redirect()->route('dashboard')->with('error', 'The Microsoft sign-in could not be verified. Please try connecting again.');
        }

        $authCode = request('code');
        $permissionType = PermissionType::from(session('outlook_permission_type', PermissionType::READ->value));

        // Clear the session value after retrieving it
        session()->forget('outlook_permission_type');

        $workspace = auth()->user()->getSelectedWorkspace();

        $outlookAccount = $this->outlookService->authenticateOutlookAccount(
            $authCode,
            $permissionType,
            $workspace,
        );

        FunnelTracking::calendarConnected($workspace, $outlookAccount->wasRecentlyCreated);

        return redirect()->route('dashboard')->with('success', 'Microsoft account "'.$outlookAccount->email.'" has been connected successfully.');
    }

    public function setBookingMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'outlook_account_id' => [
                'required',
                Rule::exists('outlook_accounts', 'id'),
            ],
            'booking_method' => ['required', new Enum(OutlookBookingMethod::class)],
        ]);

        $outlookAccount = OutlookAccount::findOrFail($request->outlook_account_id);

        $this->authorize('update', $outlookAccount);

        if ($request->booking_method === OutlookBookingMethod::ADMIN_CONSENT->value) {
            // Don't save admin_consent yet — only confirm it after the consent callback
            // returns admin_consent=True. The callback runs without a Spacepad session (the
            // M365 admin is usually someone else), so it can only trust a nonce we minted
            // here: single use, short lived, and resolved to the account server side.
            $nonce = Str::random(40);
            cache()->put(self::adminConsentCacheKey($nonce), $outlookAccount->id, now()->addHour());

            $consentUrl = $this->outlookService->getAdminConsentUrl($nonce);

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

    /**
     * Microsoft redirects here after a tenant admin approves the app. There is no Spacepad
     * session to lean on, so the request is trusted only when it carries the nonce that
     * setBookingMethod minted for this account, and the tenant that granted consent is the
     * tenant the account belongs to.
     */
    private function handleAdminConsent(): Response
    {
        $nonce = request('state');
        $accountId = is_string($nonce) && $nonce !== ''
            ? cache()->pull(self::adminConsentCacheKey($nonce))
            : null;
        $account = $accountId ? OutlookAccount::find($accountId) : null;
        $tenant = request('tenant');

        if (! $account || ! is_string($tenant) || $tenant === '') {
            logger()->warning('Admin consent callback refused: unknown or reused nonce', [
                'has_state' => is_string($nonce) && $nonce !== '',
                'has_tenant' => is_string($tenant) && $tenant !== '',
                'ip' => request()->ip(),
            ]);

            return response()->view('outlook.admin-consent-invalid', [], 403);
        }

        if ($account->tenant_id && ! hash_equals($account->tenant_id, $tenant)) {
            logger()->warning('Admin consent callback refused: tenant does not match the account', [
                'account_id' => $account->id,
                'ip' => request()->ip(),
            ]);

            return response()->view('outlook.admin-consent-invalid', [], 403);
        }

        $account->update([
            'booking_method' => OutlookBookingMethod::ADMIN_CONSENT,
            // Older rows were connected before the tenant was recorded; the consent
            // callback is the first place we learn it for sure.
            'tenant_id' => $account->tenant_id ?: $tenant,
        ]);

        logger()->info('Admin consent granted and saved', [
            'account_id' => $account->id,
        ]);

        return response()->view('outlook.admin-consent-granted');
    }

    private static function adminConsentCacheKey(string $nonce): string
    {
        return 'outlook_admin_consent:'.$nonce;
    }

    public function delete(OutlookAccount $outlookAccount): RedirectResponse
    {
        $this->authorize('delete', $outlookAccount);

        if ($outlookAccount->calendars()->exists()) {
            return redirect()->route('dashboard')->with('error', 'Cannot disconnect this account because it is used by one or more displays.');
        }

        $outlookAccount->delete();

        return redirect()->route('dashboard')->with('status', 'Outlook account has been removed successfully.');
    }
}
