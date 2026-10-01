<?php

namespace App\Support;

/**
 * Sends a notification after the HTTP response has already been flushed to
 * the browser (dispatch()->afterResponse()), rather than queuing it onto the
 * `jobs` table — that would need a worker process running continuously,
 * which isn't guaranteed in local dev (e.g. plain Valet). This keeps the
 * triggering request fast without depending on any extra process.
 *
 * The actual send runs during PHP's termination phase, outside any request
 * exception handler, so a failure there (e.g. an SMTP error) would otherwise
 * be an uncaught exception at shutdown — it's caught and reported instead.
 */
class DeferredNotification
{
    /**
     * @param  object  $recipient  Any model using the Notifiable trait.
     */
    public static function send(object $recipient, object $notification): void
    {
        dispatch(function () use ($recipient, $notification) {
            try {
                $recipient->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }
}
