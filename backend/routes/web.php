<?php

use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OutlookAccountsController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\OutlookWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('auth')->group(function () {
    Route::get('/microsoft/redirect', [MicrosoftController::class, 'redirect'])->name('auth.microsoft.redirect');
    Route::get('/microsoft/callback', [MicrosoftController::class, 'callback']);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard')->middleware('user.onboarded');
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding');

    Route::get('/outlook-accounts/auth', [OutlookAccountsController::class, 'auth'])->name('outlook-accounts.auth');
    Route::get('/outlook-accounts/callback', [OutlookAccountsController::class, 'callback']);
    Route::get('/outlook-accounts/calendars', [OutlookAccountsController::class, 'getCalendars']);

    Route::get('/displays/create', [DisplayController::class, 'create'])
        ->name('displays.create');
    Route::post('/displays', [DisplayController::class, 'store'])->name('displays.store');
    Route::patch('/displays/{display}/status', [DisplayController::class, 'updateStatus'])
        ->name('displays.updateStatus');
    Route::delete('/displays/{display}', [DisplayController::class, 'delete'])->name('displays.delete');

    Route::get('/rooms/outlook/{id}', [RoomController::class, 'outlook'])
        ->name('rooms.outlook');
});
