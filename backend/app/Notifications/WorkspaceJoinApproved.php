<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone that their request to join a workspace was approved.
 */
class WorkspaceJoinApproved extends Notification
{
    use Queueable;

    public function __construct(public string $workspaceName, public string $url) {}

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
            ->subject("You're in: {$this->workspaceName} on Spacepad")
            ->greeting('Good news,')
            ->line("Your request to join {$this->workspaceName} was approved. You see the same rooms, displays and boards as your colleagues now.")
            ->action('Open Spacepad', $this->url)
            ->salutation('Spacepad');
    }
}
