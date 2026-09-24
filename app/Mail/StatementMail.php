<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StatementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $statement
     */
    public function __construct(
        public Customer $customer,
        public array $statement,
        public string $pdfData,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->customer->company;

        return new Envelope(
            from: $company?->email_from_name
                ? new Address(config('mail.from.address'), $company->email_from_name)
                : null,
            replyTo: $company?->email_reply_to ? [new Address($company->email_reply_to)] : [],
            subject: 'Statement of account — '.($company?->name ?? config('app.name')),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.template',
            with: [
                'body' => "Hi {$this->customer->name},\n\nPlease find your statement of account attached.\n\nClosing balance: {$this->statement['closing_display']}",
                'companyName' => $this->customer->company?->name ?? config('app.name'),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfData, 'statement-'.$this->customer->id.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
