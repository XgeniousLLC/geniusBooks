<?php

namespace App\Services\Accounting;

use App\Mail\PaymentReceivedMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Services\EmailLogger;
use App\Services\EmailTemplateService;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class PaymentReceiptService
{
    public function __construct(
        private readonly EmailTemplateService $templates,
        private readonly EmailLogger $logger,
    ) {}

    public function send(Payment $payment): void
    {
        $payment->loadMissing(['customer', 'company']);
        $company = $payment->company;

        $to = $payment->customer?->email;

        if (! $to) {
            throw new RuntimeException('The customer does not have an email address.');
        }

        $rendered = $this->templates->render($company, EmailTemplate::PAYMENT_RECEIVED, [
            'customer_name' => $payment->customer->name,
            'invoice_number' => $payment->invoices->pluck('number')->implode(', ') ?: 'your account',
            'invoice_amount' => Money::of((int) $payment->amount, $payment->currency())->format(),
        ]);

        Mail::to($to)->queue(new PaymentReceivedMail(
            $payment,
            $rendered['subject'],
            $rendered['body'],
            $company->email_reply_to ?: $company->email,
            $company->email_from_name,
        ));

        $this->logger->log($company->id, $to, $rendered['subject'], EmailLog::STATUS_QUEUED, PaymentReceivedMail::class);
    }
}
