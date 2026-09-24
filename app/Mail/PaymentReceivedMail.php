<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public string $mailSubject,
        public string $mailBody,
        public ?string $replyToAddress = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        $from = $this->fromName ? new Address(config('mail.from.address'), $this->fromName) : null;
        $replyTo = $this->replyToAddress ? [new Address($this->replyToAddress)] : [];

        return new Envelope(from: $from, replyTo: $replyTo, subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.template',
            with: [
                'body' => $this->mailBody,
                'companyName' => $this->payment->company->name,
            ],
        );
    }
}
