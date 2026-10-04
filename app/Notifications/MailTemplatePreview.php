<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A sample notification with no real event behind it — sent only from the
 * Settings page so an admin can see exactly how the shared mail template
 * (brand color, button, footer note) renders in a real inbox before
 * committing to it.
 */
class MailTemplatePreview extends Notification
{
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
            ->subject('Preview: '.config('app.name').' notification email')
            ->greeting('Hi there,')
            ->line('This is a preview of what your team sees when a task or project notification goes out.')
            ->line('The header, button and footer below reflect your current Email Template settings.')
            ->action('Sample Button', url('/'))
            ->line('Thanks for using '.config('app.name').'!');
    }
}
