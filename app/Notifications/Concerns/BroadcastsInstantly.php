<?php

namespace App\Notifications\Concerns;

use App\Support\NotificationChannels;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Pushes a notification to the recipient's browser the moment it's sent, over
 * Reverb on the private App.Models.User.{id} channel. It goes out on the sync
 * connection because the app deliberately runs without a queue worker (see
 * DeferredNotification), and after the database record but before the slower
 * email, so the browser hears about it right away and can find the record.
 */
trait BroadcastsInstantly
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // In-app (database + live push) and email can each be switched off in Settings → Notifications.
        return NotificationChannels::for($notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
