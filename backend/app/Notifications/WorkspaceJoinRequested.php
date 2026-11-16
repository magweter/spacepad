<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owners and admins of a workspace that a colleague asks to join it.
 */
class WorkspaceJoinRequested extends Notification
{
    use Queueable;

    public function __construct(
        public string $requesterName,
        public string $requesterEmail,
        public string $workspaceName,
        public string $reviewUrl,
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
        return (new MailMessage)
            ->subject("{$this->requesterName} asks to join {$this->workspaceName} on Spacepad")
            ->greeting('Hello,')
            ->line("{$this->requesterName} ({$this->requesterEmail}) signed up for Spacepad and asks to join {$this->workspaceName}, so your rooms and displays stay in one place.")
            ->action('Review the request', $this->reviewUrl)
            ->line('Not a colleague? Decline the request; they keep a workspace of their own.')
            ->salutation('Spacepad');
    }
}
