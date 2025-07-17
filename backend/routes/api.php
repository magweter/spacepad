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
    Route::put('devices/display', [DeviceController::class, 'changeDisplay']); # Deprecated > v1.2.0

    Route::get('displays', [DisplayController::class, 'index']);
    Route::get('displays/{display}/data', [DisplayController::class, 'getData']);
    Route::post('displays/{display}/book', [DisplayController::class, 'book']);
    Route::post('displays/{display}/events/{eventId}/check-in', [DisplayController::class, 'checkIn']);
    Route::delete('displays/{display}/events/{eventId}', [DisplayController::class, 'cancel']);

    Route::get('events', [EventController::class, 'index']); # Deprecated > v1.2.0
});

Route::post('webhook/outlook', [OutlookWebhookController::class, 'handleNotification']);
Route::post('webhook/google', [GoogleWebhookController::class, 'handleNotification']);

Route::prefix('v1')->group(function () {
    Route::post('/instances/activate', [InstanceController::class, 'activate']);
    Route::post('/instances/heartbeat', [InstanceController::class, 'heartbeat']);
    Route::post('/instances/validate', [InstanceController::class, 'validateInstance']);
});
