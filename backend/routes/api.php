<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\DeviceController;
use App\Http\Controllers\API\DisplayController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\OutlookWebhookController;
use App\Http\Controllers\GoogleWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('devices/me', [DeviceController::class, 'getMe']);
    Route::put('devices/display', [DeviceController::class, 'changeDisplay']);
    Route::get('displays', [DisplayController::class, 'getDisplays']);
    Route::get('events', [EventController::class, 'getAll']);
});

Route::post('webhook/outlook', [OutlookWebhookController::class, 'handleNotification']);
Route::post('webhook/google', [GoogleWebhookController::class, 'handleNotification']);
