<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation to join a workspace.
 *
 * Deliberately NOT ShouldQueue: there is no queue:work service in production
 * (docker-compose.prod.yml only runs schedule:work) while .env sets
 * QUEUE_CONNECTION=database, so queueing this would silently swallow every invitation.
 * Matches MagicLoginNotification, which sends synchronously for the same reason.
 */
class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $acceptUrl,
        public string $workspaceName,
        public string $inviterName,
        public string $roleLabel,
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
            ->subject("{$this->inviterName} invited you to {$this->workspaceName} - ".config('app.name'))
            ->greeting('Hello! 👋')
            ->line("{$this->inviterName} invited you to join the workspace \"{$this->workspaceName}\" on ".config('app.name')." as {$this->roleLabel}.")
            ->line('Joining means you and your colleagues manage the same displays, boards and calendar accounts together.')
            ->action('Accept invitation', $this->acceptUrl)
            ->line('This invitation expires in '.WorkspaceInvitation::LIFETIME_DAYS.' days.')
            ->line("If you weren't expecting this invitation, you can safely ignore this email.")
            ->salutation('Best regards,');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
