<?php

namespace App\Http\Controllers;

use App\Enums\PermissionType;
use App\Enums\GoogleBookingMethod;
use App\Models\GoogleAccount;
use App\Services\GoogleService;
use Google\Service\Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GoogleAccountsController extends Controller
{
    protected GoogleService $googleService;

    public function __construct(GoogleService $googleService)
    {
        $this->googleService = $googleService;
    }

    public function setBookingMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'booking_method' => ['required', Rule::in(['service_account', 'user_account'])],
        ]);

        // Store booking method in session - will be saved to account after OAuth callback
        session(['google_booking_method' => $request->booking_method]);

        // Permission type should already be in session, proceed to OAuth
        $permissionType = PermissionType::from(session('google_permission_type', PermissionType::READ->value));
        return redirect($this->googleService->getAuthUrl($permissionType));
    }

    public function auth(Request $request): RedirectResponse
    {
        $request->validate([
            'permission_type' => ['required', new Enum(PermissionType::class)],
        ]);

        $permissionType = PermissionType::from($request->permission_type);

        // Store permission type in session
        session(['google_permission_type' => $request->permission_type]);

        // If write permission is selected, we need booking method first
        if ($permissionType === PermissionType::WRITE && !session()->has('google_booking_method')) {
            return redirect()->back()->with('open-google-booking-method-modal', true);
        }

        // Booking method should already be in session from setBookingMethod, proceed to OAuth
        return redirect($this->googleService->getAuthUrl($permissionType));
    }

    /**
     * Handle service account file upload for workspace accounts.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function uploadServiceAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'google_account_id' => [
                'required',
                Rule::exists('google_accounts', 'id')->where('user_id', auth()->id()),
            ],
            'service_account_file' => [
                'required',
                'file',
                'mimes:json',
                'max:50', // Max 50KB
                function ($attribute, $value, $fail) {
                    $content = file_get_contents($value->getRealPath());
                    $json = json_decode($content, true);
                    
                    if (!$json || !isset($json['type']) || $json['type'] !== 'service_account') {
                        $fail('The file must be a valid Google Service Account JSON file.');
                    }
                    
                    $required = ['private_key', 'client_email', 'project_id'];
                    foreach ($required as $field) {
                        if (!isset($json[$field])) {
                            $fail("The service account file is missing required field: {$field}");
                        }
                    }
                },
            ],
        ]);

        $googleAccount = GoogleAccount::where('id', $request->google_account_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Ensure it's a workspace account
        if (! $googleAccount->isBusiness()) {
            return redirect()->route('dashboard')->with('error', 'Service account is only required for Google Workspace accounts.');
        }

        // Store file in user-specific directory
        $userDir = 'google-service-accounts/' . auth()->id();
        $fileName = 'google-account-' . $googleAccount->id . '-' . time() . '.json';
        $filePath = $userDir . '/' . $fileName;
        
        // Delete old file if exists
        if ($googleAccount->service_account_file_path && Storage::exists($googleAccount->service_account_file_path)) {
            Storage::delete($googleAccount->service_account_file_path);
        }

        // Read file content and encrypt it before storing
        $fileContent = file_get_contents($request->file('service_account_file')->getRealPath());
        $encryptedContent = Crypt::encryptString($fileContent);
        
        // Store encrypted file - explicitly construct path to ensure correct value is stored
        if (! Storage::put($filePath, $encryptedContent)) {
            return redirect()->route('dashboard')->with('error', 'Failed to save service account file. Please try again.');
        }

        // Update account with file path and upgrade to WRITE permission
        $googleAccount->update([
            'service_account_file_path' => $filePath,
            'permission_type' => PermissionType::WRITE,
        ]);

        return redirect()->route('dashboard')->with('success', 'Service account file uploaded successfully. Your account has been upgraded to Read & Write. You can now book rooms directly.');
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
        $bookingMethod = GoogleBookingMethod::from(session('google_booking_method', GoogleBookingMethod::USER_ACCOUNT->value));

        // Clear the session values after retrieving them
        session()->forget('google_permission_type');
        session()->forget('google_booking_method');

        $googleAccount = $this->googleService->authenticateGoogleAccount($authCode, $permissionType, $bookingMethod);

        return redirect()->route('dashboard')->with('success', 'Google account "' . $googleAccount->email . '" has been connected successfully.');
    }

    public function delete(GoogleAccount $googleAccount): RedirectResponse
    {
        if ($googleAccount->calendars()->exists()) {
            return redirect()->route('dashboard')->with('error', 'Cannot disconnect this account because it is used by one or more displays.');
        }

        // Delete service account file if it exists
        if ($googleAccount->service_account_file_path && Storage::exists($googleAccount->service_account_file_path)) {
            Storage::delete($googleAccount->service_account_file_path);
        }

        $googleAccount->delete();

        return redirect()->route('dashboard')->with('status', 'Google account has been removed successfully.');
    }
}
