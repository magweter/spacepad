<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\RegistrationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Http;

class SendRegistrationNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        $webhookUrl = config('settings.registration_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        // Example JSON payload:
        // {
        //     "user_id": 123,
        //     "email": "john.doe@example.com", 
        //     "name": "John Doe",
        //     "event": "registration"
        // }
        Http::post($webhookUrl, [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'name' => $event->user->name,
            'event' => 'registration',
        ]);
    }
}
