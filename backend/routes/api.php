<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Cloud\InstanceController;
use App\Http\Controllers\API\DeviceController;
use App\Http\Controllers\API\DisplayController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\GoogleWebhookController;
use App\Http\Controllers\OutlookWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'user.update-last-activity'])->group(function () {
    Route::get('devices/me', [DeviceController::class, 'me']);
    Route::put('devices/display', [DeviceController::class, 'changeDisplay']);
    Route::get('displays', [DisplayController::class, 'index']);
    Route::get('events', [EventController::class, 'index']);
    Route::post('events/book', [EventController::class, 'book']);
    Route::delete('events/{eventId}', [EventController::class, 'cancel']);
});

Route::post('webhook/outlook', [OutlookWebhookController::class, 'handleNotification']);
Route::post('webhook/google', [GoogleWebhookController::class, 'handleNotification']);

Route::prefix('v1')->group(function () {
    Route::post('/instances/activate', [InstanceController::class, 'activate']);
    Route::post('/instances/heartbeat', [InstanceController::class, 'heartbeat']);
    Route::post('/instances/validate', [InstanceController::class, 'validateInstance']);
});
