<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Notification;

/**
 * The broadcast channel, but a failed push (e.g. the Reverb server isn't
 * running) is reported instead of thrown — so it never stops the database
 * record or the email that are sent alongside it.
 */
class SafeBroadcastChannel extends BroadcastChannel
{
    public function send($notifiable, Notification $notification): ?array
    {
        try {
            return parent::send($notifiable, $notification);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
