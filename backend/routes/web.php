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
use App\Http\Controllers\OutlookWebhookController;
use App\Http\Controllers\CalDAVAccountsController;
use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

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

Route::middleware(['auth', 'user.update-last-activity'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard')->middleware('user.active');
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding')->middleware('user.onboarding');
    Route::post('/onboarding/usage-type', [OnboardingController::class, 'updateUsageType'])->name('onboarding.usage-type');
    Route::post('/onboarding/terms', [OnboardingController::class, 'acceptTerms'])->name('onboarding.terms');

    Route::get('/outlook-accounts/auth', [OutlookAccountsController::class, 'auth'])->name('outlook-accounts.auth');
    Route::get('/outlook-accounts/callback', [OutlookAccountsController::class, 'callback']);
    Route::get('/outlook-accounts/calendars', [OutlookAccountsController::class, 'getCalendars']);

    Route::get('/google-accounts/auth', [GoogleAccountsController::class, 'auth'])->name('google-accounts.auth');
    Route::get('/google-accounts/callback', [GoogleAccountsController::class, 'callback']);
    Route::get('/google-accounts/calendars', [GoogleAccountsController::class, 'getCalendars']);

    Route::get('/caldav-accounts/create', [CalDAVAccountsController::class, 'create'])->name('caldav-accounts.create');
    Route::post('/caldav-accounts', [CalDAVAccountsController::class, 'store'])->name('caldav-accounts.store');
    Route::delete('/caldav-accounts/{caldavAccount}', [CalDAVAccountsController::class, 'delete'])->name('caldav-accounts.delete');

    Route::get('/displays/create', [DisplayController::class, 'create'])
        ->name('displays.create');
    Route::post('/displays', [DisplayController::class, 'store'])->name('displays.store');
    Route::patch('/displays/{display}/status', [DisplayController::class, 'updateStatus'])
        ->name('displays.updateStatus');
    Route::delete('/displays/{display}', [DisplayController::class, 'delete'])->name('displays.delete');

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
});
