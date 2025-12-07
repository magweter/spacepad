<?php

namespace App\Http\Controllers;

use App\Enums\PermissionType;
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

    public function auth(Request $request): RedirectResponse
    {
        $request->validate([
            'permission_type' => ['required', new Enum(PermissionType::class)],
        ]);

        $permissionType = PermissionType::from($request->permission_type);

        // For workspace accounts with write permission, we need service account file first
        // Store permission type in session and check if we need service account upload
        session(['google_permission_type' => $request->permission_type]);

        // Check if this is a workspace account (we'll determine this after OAuth)
        // For now, proceed with OAuth - we'll check in callback if service account is needed
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
        if (!$googleAccount->isBusiness()) {
            return redirect()->route('dashboard')->with('error', 'Service account is only required for Google Workspace accounts.');
        }

        // Store file in user-specific directory
        $userDir = 'google-service-accounts/' . auth()->id();
        $fileName = 'google-account-' . $googleAccount->id . '-' . time() . '.json';
        
        // Delete old file if exists
        if ($googleAccount->service_account_file_path && Storage::exists($googleAccount->service_account_file_path)) {
            Storage::delete($googleAccount->service_account_file_path);
        }

        // Read file content and encrypt it before storing
        $fileContent = file_get_contents($request->file('service_account_file')->getRealPath());
        $encryptedContent = Crypt::encryptString($fileContent);
        
        // Store encrypted file
        $filePath = Storage::put($userDir . '/' . $fileName, $encryptedContent);

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

        // Delete service account file if it exists
        if ($googleAccount->service_account_file_path && Storage::exists($googleAccount->service_account_file_path)) {
            Storage::delete($googleAccount->service_account_file_path);
        }

        $googleAccount->delete();

        return redirect()->route('dashboard')->with('status', 'Google account has been removed successfully.');
    }
}
