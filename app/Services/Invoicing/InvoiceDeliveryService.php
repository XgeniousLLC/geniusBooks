<?php

namespace App\Services\Invoicing;

use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Services\Documents\InvoicePdfService;
use App\Services\EmailLogger;
use App\Services\EmailTemplateService;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Renders and queues invoice emails (initial send and reminders) with the
 * invoice PDF attached, and records a delivery log entry.
 */
class InvoiceDeliveryService
{
    public function __construct(
        private readonly InvoicePdfService $pdf,
        private readonly EmailTemplateService $templates,
        private readonly EmailLogger $logger,
    ) {}

    public function send(Invoice $invoice, string $templateKey = EmailTemplate::INVOICE_SENT): void
    {
        $invoice->loadMissing(['customer', 'company']);
        $company = $invoice->company;

        $to = $invoice->customer?->email;

        if (! $to) {
            throw new RuntimeException('The customer does not have an email address.');
        }

        $rendered = $this->templates->render($company, $templateKey, $this->templateData($invoice));

        $mail = new InvoiceMail(
            $invoice,
            $rendered['subject'],
            $rendered['body'],
            $this->pdf->output($invoice),
            $this->pdf->filename($invoice),
            $company->email_reply_to ?: $company->email,
            $company->email_from_name,
        );

        Mail::to($to)->queue($mail);

        $this->logger->log(
            $company->id,
            $to,
            $rendered['subject'],
            EmailLog::STATUS_QUEUED,
            InvoiceMail::class,
        );
    }

    /**
     * @return array<string, string>
     */
    public function templateData(Invoice $invoice): array
    {
        return [
            'customer_name' => $invoice->customer->name,
            'invoice_number' => $invoice->number,
            'invoice_amount' => Money::of((int) $invoice->total, $invoice->currency)->format(),
            'due_date' => $invoice->due_date->toFormattedDateString(),
            'invoice_url' => $this->publicUrl($invoice),
        ];
    }

    public function publicUrl(Invoice $invoice, int $days = 30): string
    {
        return URL::temporarySignedRoute(
            'portal.invoices.public',
            now()->addDays($days),
            ['invoice' => $invoice->id],
        );
    }

    public function publicPdfUrl(Invoice $invoice, int $days = 30): string
    {
        return URL::temporarySignedRoute(
            'portal.invoices.public.pdf',
            now()->addDays($days),
            ['invoice' => $invoice->id],
        );
    }
}
