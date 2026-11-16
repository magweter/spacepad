<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks an IT admin to connect the room calendars, sent by someone setting up a display without
 * the admin rights to do it themselves. Explains what Spacepad reads and links to the consent.
 */
class ItAdminCalendarRequest extends Notification
{
    use Queueable;

    public function __construct(
        public string $requesterName,
        public string $requesterEmail,
        public string $workspaceName,
        public ?string $microsoftConsentUrl,
        public string $setupUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("{$this->requesterName} asked you to connect Spacepad to your room calendars")
            ->replyTo($this->requesterEmail, $this->requesterName)
            ->greeting('Hello,')
            ->line("{$this->requesterName} ({$this->requesterEmail}) is setting up Spacepad room displays for {$this->workspaceName} and needs an admin to connect the room calendars.")
            ->line('Spacepad shows on a tablet next to each meeting room whether the room is free. It reads the room calendars you allow. It keeps the bookings of the current day to keep the display fast, and deletes them when the day is over. It writes to a calendar only when booking from the display is switched on.');

        if ($this->microsoftConsentUrl) {
            $mail->line('Microsoft 365: approve Spacepad for your organisation with the button below. You sign in with an admin account and see exactly which permissions are asked.')
                ->action('Approve for Microsoft 365', $this->microsoftConsentUrl);
        }

        return $mail
            ->line("Google Workspace or CalDAV: sign in with an admin account at {$this->setupUrl} and connect the calendar there.")
            ->line("Questions? Reply to this email to reach {$this->requesterName}.")
            ->salutation('Spacepad');
    }
}
