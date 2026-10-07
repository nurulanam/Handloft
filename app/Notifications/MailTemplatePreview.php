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
        // A realistic sample, so the preview looks like the emails people actually get.
        return (new MailMessage)
            ->subject('Preview: Rahim assigned you "Redesign the onboarding emails"')
            ->greeting('Hi there,')
            ->line('Rahim assigned you **Redesign the onboarding emails** in *Website Relaunch*. It\'s due on Friday.')
            ->action('View task', url('/'))
            ->line('You\'re getting this because work in '.config('app.name').' was handed to you.');
    }
}
