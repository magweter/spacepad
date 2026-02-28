<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\GoogleAccountsController;
use App\Http\Controllers\OutlookAccountsController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\DisplaySettingsController;
use App\Http\Controllers\OutlookWebhookController;
use App\Http\Controllers\CalDAVAccountsController;
use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\UsageController;

Route::get('/login', [LoginController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::get('/register', [RegisterController::class, 'create'])
    ->middleware('guest')
    ->name('register');

Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('guest')
    ->name('register.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('auth')->group(function () {
    Route::get('/microsoft/redirect', [MicrosoftController::class, 'redirect'])->name('auth.microsoft.redirect');
    Route::get('/microsoft/callback', [MicrosoftController::class, 'callback']);
    Route::get('/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/google/callback', [GoogleController::class, 'callback']);
});

Route::middleware(['auth', 'user.update-last-activity', 'gtm'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard')->middleware('user.active');
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding')->middleware('user.onboarding');
    Route::post('/onboarding/usage-type', [OnboardingController::class, 'updateUsageType'])->name('onboarding.usage-type');
    Route::post('/onboarding/terms', [OnboardingController::class, 'acceptTerms'])->name('onboarding.terms');

    Route::post('/outlook-accounts/auth', [OutlookAccountsController::class, 'auth'])->name('outlook-accounts.auth');
    Route::get('/outlook-accounts/callback', [OutlookAccountsController::class, 'callback']);
    Route::get('/outlook-accounts/calendars', [OutlookAccountsController::class, 'getCalendars']);
    Route::delete('/outlook-accounts/{outlookAccount}', [OutlookAccountsController::class, 'delete'])->name('outlook-accounts.delete');

    Route::post('/google-accounts/booking-method', [GoogleAccountsController::class, 'setBookingMethod'])->name('google-accounts.set-booking-method');
    Route::post('/google-accounts/auth', [GoogleAccountsController::class, 'auth'])->name('google-accounts.auth');
    Route::post('/google-accounts/service-account', [GoogleAccountsController::class, 'uploadServiceAccount'])->name('google-accounts.service-account');
    Route::get('/google-accounts/callback', [GoogleAccountsController::class, 'callback']);
    Route::get('/google-accounts/calendars', [GoogleAccountsController::class, 'getCalendars']);
    Route::delete('/google-accounts/{googleAccount}', [GoogleAccountsController::class, 'delete'])->name('google-accounts.delete');

    Route::get('/caldav-accounts/create', [CalDAVAccountsController::class, 'create'])->name('caldav-accounts.create');
    Route::post('/caldav-accounts', [CalDAVAccountsController::class, 'store'])->name('caldav-accounts.store');
    Route::delete('/caldav-accounts/{caldavAccount}', [CalDAVAccountsController::class, 'delete'])->name('caldav-accounts.delete');

    Route::get('/displays/create', [DisplayController::class, 'create'])
        ->name('displays.create');
    Route::post('/displays', [DisplayController::class, 'store'])->name('displays.store');
    Route::patch('/displays/{display}/status', [DisplayController::class, 'updateStatus'])
        ->name('displays.updateStatus');
    Route::delete('/displays/{display}', [DisplayController::class, 'delete'])->name('displays.delete');

    // Display settings routes
    Route::get('/displays/{display}/settings', [DisplaySettingsController::class, 'index'])
        ->name('displays.settings.index');
    Route::put('/displays/{display}/settings', [DisplaySettingsController::class, 'update'])
        ->name('displays.settings.update');

    // Display customization routes
    Route::get('/displays/{display}/customization', [DisplaySettingsController::class, 'customization'])
        ->name('displays.customization');
    Route::put('/displays/{display}/customization', [DisplaySettingsController::class, 'updateCustomization'])
        ->name('displays.customization.update');

    Route::get('/calendars/outlook/{id}', [CalendarController::class, 'outlook'])
        ->name('calendars.outlook');
    Route::get('/calendars/google/{id}', [CalendarController::class, 'google'])
        ->name('calendars.google');
    Route::get('/calendars/caldav/{id}', [CalendarController::class, 'caldav'])
        ->name('calendars.caldav');
    Route::get('/rooms/outlook/{id}', [RoomController::class, 'outlook'])
        ->name('rooms.outlook');
    Route::get('/rooms/google/{id}', [RoomController::class, 'google'])
        ->name('rooms.google');

    Route::post('/license/validate', [LicenseController::class, 'validateLicense'])->name('license.validate');

    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    Route::get('/billing/thanks', function () {
        \Spatie\GoogleTagManager\GoogleTagManagerFacade::flashPush([
            'event' => 'purchase',
        ]);
        if (config('services.google_conversion.send_to')) {
            \Spatie\GoogleTagManager\GoogleTagManagerFacade::flashPush([
                'event' => 'conversion',
                'send_to' => config('services.google_conversion.send_to'),
                'value' => config('services.google_conversion.value'),
                'currency' => config('services.google_conversion.currency'),
                'transaction_id' => '',
            ]);
        }
        return redirect()->route('dashboard');
    })->name('billing.thanks');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/admin/users/{user}', [AdminController::class, 'showUser'])->name('admin.users.show');
    Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::post('/admin/users/{user}/impersonate', [AdminController::class, 'impersonate'])->name('admin.users.impersonate');
    Route::post('/admin/stop-impersonating', [AdminController::class, 'stopImpersonating'])->name('admin.stop-impersonating');

    // Display image serving route
    Route::get('/displays/{display}/images/{type}', [DisplaySettingsController::class, 'serveImage'])
        ->name('displays.images');

    // Boards routes
    Route::get('/boards/create', [BoardController::class, 'create'])->name('boards.create');
    Route::post('/boards', [BoardController::class, 'store'])->name('boards.store');
    Route::get('/boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::get('/boards/{board}/edit', [BoardController::class, 'edit'])->name('boards.edit');
    Route::put('/boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::delete('/boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
    Route::get('/boards/{board}/images/logo', [BoardController::class, 'serveLogo'])->name('boards.images.logo');

    // Usage routes
    Route::get('/usage', [UsageController::class, 'index'])->name('usage.index');
});
