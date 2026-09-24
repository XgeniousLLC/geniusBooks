<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Mail\TestEmail;
use App\Models\Company;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\EmailLogger;
use App\Services\EmailTemplateService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsController extends Controller
{
    public function __construct(
        private readonly EmailTemplateService $templates,
        private readonly EmailLogger $logger,
    ) {}

    public function index(): Response
    {
        Gate::authorize(Permission::ManageSettings);

        $company = $this->company();
        $this->templates->ensureDefaults($company);

        $templates = EmailTemplate::query()
            ->where('company_id', $company->id)
            ->orderBy('key')
            ->get()
            ->map(fn (EmailTemplate $template) => [
                'key' => $template->key,
                'label' => $template->label(),
                'subject' => $template->subject,
                'body' => $template->body,
            ])
            ->values();

        $logs = EmailLog::query()
            ->where('company_id', $company->id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (EmailLog $log) => [
                'id' => $log->id,
                'to' => $log->to_email,
                'subject' => $log->subject,
                'status' => $log->status,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Settings/Email', [
            'settings' => [
                'email_from_name' => $company->email_from_name,
                'email_reply_to' => $company->email_reply_to,
                'payment_instructions' => $company->payment_instructions,
                'invoice_footer' => $company->invoice_footer,
                'reminders_enabled' => (bool) $company->reminders_enabled,
                'reminder_days_before' => $company->reminder_days_before,
                'reminder_days_overdue' => $company->reminder_days_overdue,
                'notify_invoice_sent' => (bool) $company->notify_invoice_sent,
                'notify_payment_received' => (bool) $company->notify_payment_received,
                'notify_invoice_due' => (bool) $company->notify_invoice_due,
                'notify_invoice_overdue' => (bool) $company->notify_invoice_overdue,
            ],
            'templates' => $templates,
            'logs' => $logs,
            'variables' => [
                'customer_name', 'invoice_number', 'invoice_amount', 'due_date', 'invoice_url',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageSettings);

        $data = $request->validate([
            'email_from_name' => ['nullable', 'string', 'max:255'],
            'email_reply_to' => ['nullable', 'email', 'max:255'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
            'reminders_enabled' => ['boolean'],
            'reminder_days_before' => ['required', 'integer', 'between:0,60'],
            'reminder_days_overdue' => ['required', 'integer', 'between:0,60'],
            'notify_invoice_sent' => ['boolean'],
            'notify_payment_received' => ['boolean'],
            'notify_invoice_due' => ['boolean'],
            'notify_invoice_overdue' => ['boolean'],
        ]);

        $data['reminders_enabled'] = $request->boolean('reminders_enabled');
        $data['notify_invoice_sent'] = $request->boolean('notify_invoice_sent');
        $data['notify_payment_received'] = $request->boolean('notify_payment_received');
        $data['notify_invoice_due'] = $request->boolean('notify_invoice_due');
        $data['notify_invoice_overdue'] = $request->boolean('notify_invoice_overdue');

        $this->company()->update($data);

        return back()->with('success', 'Delivery settings updated.');
    }

    public function updateTemplate(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageSettings);

        $data = $request->validate([
            'key' => ['required', Rule::in(EmailTemplate::KEYS)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $company = $this->company();

        EmailTemplate::withoutCompanyScope()->updateOrCreate(
            ['company_id' => $company->id, 'key' => $data['key']],
            ['subject' => $data['subject'], 'body' => $data['body']],
        );

        return back()->with('success', 'Template saved.');
    }

    public function testSend(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageSettings);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $company = $this->company();
        $subject = 'Test email from '.$company->name;

        Mail::to($data['email'])->send(new TestEmail($subject));

        $this->logger->log($company->id, $data['email'], $subject, EmailLog::STATUS_QUEUED, TestEmail::class);

        return back()->with('success', 'Test email sent to '.$data['email'].'.');
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}
