<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Transactional test email used to verify outbound delivery configuration.
 */
class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $messageText = 'Outbound email is configured correctly.') {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xgenious Accounting — test email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test',
            with: ['messageText' => $this->messageText],
        );
    }
}
