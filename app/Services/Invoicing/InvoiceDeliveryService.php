<?php

namespace App\Services\Invoicing;

use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Services\Documents\InvoicePdfService;
use App\Services\EmailLogger;
use App\Services\EmailTemplateService;
use App\Services\Sms\SmsManager;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Renders and queues invoice emails (initial send and reminders) with the
 * invoice PDF attached, records a delivery log entry, and optionally sends an
 * SMS when the company has SMS notifications enabled.
 */
class InvoiceDeliveryService
{
    public function __construct(
        private readonly InvoicePdfService $pdf,
        private readonly EmailTemplateService $templates,
        private readonly EmailLogger $logger,
        private readonly SmsManager $sms,
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

        $this->sendSms($invoice, $templateKey);
    }

    /**
     * Send an accompanying SMS when enabled and the customer has a phone.
     */
    private function sendSms(Invoice $invoice, string $templateKey): void
    {
        $company = $invoice->company;
        $phone = $invoice->customer?->phone;

        if (! $company->sms_notifications_enabled || ! $phone) {
            return;
        }

        $amount = Money::of((int) $invoice->total, $invoice->currency)->format();

        $message = match ($templateKey) {
            EmailTemplate::INVOICE_DUE => "Reminder: invoice {$invoice->number} for {$amount} is due on {$invoice->due_date->toFormattedDateString()}.",
            EmailTemplate::INVOICE_OVERDUE => "Invoice {$invoice->number} for {$amount} is overdue (due {$invoice->due_date->toFormattedDateString()}).",
            default => "Invoice {$invoice->number} for {$amount} from {$company->name} is ready.",
        };

        $this->sms->send($phone, $message);
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
