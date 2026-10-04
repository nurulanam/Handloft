<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from the Settings page's "Send Test Email" button, to let an admin
 * verify newly-entered SMTP credentials actually work before saving them.
 */
class SmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test email from '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.smtp-test',
        );
    }
}
