<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EmailTemplate;

/**
 * Resolves and renders per-company email templates, falling back to built-in
 * defaults when a company has not customised a template.
 */
class EmailTemplateService
{
    /**
     * @var array<string, array{subject: string, body: string}>
     */
    public const DEFAULTS = [
        EmailTemplate::INVOICE_SENT => [
            'subject' => 'Invoice {{invoice_number}} from {{company_name}}',
            'body' => "Hi {{customer_name}},\n\nPlease find invoice {{invoice_number}} for {{invoice_amount}}, due on {{due_date}}.\n\nYou can view and download it here: {{invoice_url}}\n\nThank you,\n{{company_name}}",
        ],
        EmailTemplate::INVOICE_DUE => [
            'subject' => 'Reminder: invoice {{invoice_number}} is due on {{due_date}}',
            'body' => "Hi {{customer_name}},\n\nThis is a friendly reminder that invoice {{invoice_number}} for {{invoice_amount}} is due on {{due_date}}.\n\nView it here: {{invoice_url}}\n\nThank you,\n{{company_name}}",
        ],
        EmailTemplate::INVOICE_OVERDUE => [
            'subject' => 'Overdue: invoice {{invoice_number}}',
            'body' => "Hi {{customer_name}},\n\nInvoice {{invoice_number}} for {{invoice_amount}} is now overdue (due {{due_date}}).\n\nPlease arrange payment at your earliest convenience.\n\nView it here: {{invoice_url}}\n\nThank you,\n{{company_name}}",
        ],
        EmailTemplate::PAYMENT_RECEIVED => [
            'subject' => 'Payment received for {{invoice_number}}',
            'body' => "Hi {{customer_name}},\n\nWe have received your payment for invoice {{invoice_number}}. Thank you.\n\n{{company_name}}",
        ],
    ];

    public function ensureDefaults(Company $company): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            EmailTemplate::withoutCompanyScope()->firstOrCreate(
                ['company_id' => $company->id, 'key' => $key],
                ['subject' => $default['subject'], 'body' => $default['body']],
            );
        }
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function render(Company $company, string $key, array $data): array
    {
        $template = $this->resolve($company, $key);

        return [
            'subject' => $this->replace($template['subject'], $company, $data),
            'body' => $this->replace($template['body'], $company, $data),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function resolve(Company $company, string $key): array
    {
        $template = EmailTemplate::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('key', $key)
            ->first();

        if ($template) {
            return ['subject' => $template->subject, 'body' => $template->body];
        }

        return self::DEFAULTS[$key] ?? ['subject' => $company->name, 'body' => ''];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function replace(string $content, Company $company, array $data): string
    {
        $data = array_merge([
            'company_name' => $company->name,
        ], $data);

        $replacements = [];
        foreach ($data as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }
}
