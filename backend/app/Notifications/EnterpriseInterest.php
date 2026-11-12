<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells sales that a workspace started its trial from the Enterprise side of the upgrade dialog,
 * so someone can reach out about a demo, an SLA and a quote.
 */
class EnterpriseInterest extends Notification
{
    use Queueable;

    public function __construct(
        public string $requesterName,
        public string $requesterEmail,
        public string $workspaceName,
        public int $displays,
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
            ->subject("Enterprise interest: {$this->workspaceName}")
            ->greeting('Hello,')
            ->line("{$this->requesterName} ({$this->requesterEmail}) is starting the Pro trial for {$this->workspaceName} and would like to hear about Enterprise.")
            ->line("The workspace has {$this->displays} ".($this->displays === 1 ? 'display' : 'displays').' so far.')
            ->line('Reply to this email to plan a demo and send a quote.')
            ->replyTo($this->requesterEmail, $this->requesterName)
            ->salutation('Spacepad');
    }
}
