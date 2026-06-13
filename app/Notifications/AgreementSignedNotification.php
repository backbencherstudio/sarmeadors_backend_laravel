<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to the addresses configured on a document template
 * ("Notification Emails") whenever the agreement is signed.
 */
class AgreementSignedNotification extends Notification
{
    public function __construct(
        public string $templateName,
        public string $signerName,
        public string $signerType,
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
            ->subject(sprintf('Agreement Signed: %s', $this->templateName))
            ->line(sprintf('%s (%s) has signed "%s".', $this->signerName, $this->signerType, $this->templateName))
            ->line('Log in to your agency dashboard to view the signed document.');
    }
}
