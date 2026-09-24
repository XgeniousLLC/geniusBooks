<?php

use App\Mail\TestEmail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

it('shows email settings to an owner', function () {
    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->get('/portal/settings/email')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Email')
            ->has('templates', count(EmailTemplate::KEYS)));
});

it('forbids staff from email settings', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal/settings/email')->assertForbidden();
});

it('updates delivery settings', function () {
    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->patch('/portal/settings/email', [
        'email_from_name' => 'Acme Billing',
        'email_reply_to' => 'billing@acme.test',
        'payment_instructions' => 'Bank: 123456',
        'invoice_footer' => 'Thank you',
        'reminders_enabled' => false,
        'reminder_days_before' => 5,
        'reminder_days_overdue' => 2,
    ])->assertRedirect();

    $company->refresh();

    expect($company->email_from_name)->toBe('Acme Billing')
        ->and($company->reminders_enabled)->toBeFalse()
        ->and($company->reminder_days_before)->toBe(5);
});

it('updates an email template', function () {
    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->post('/portal/settings/email/templates', [
        'key' => EmailTemplate::INVOICE_SENT,
        'subject' => 'Your invoice {{invoice_number}}',
        'body' => 'Hello {{customer_name}}',
    ])->assertRedirect();

    $template = EmailTemplate::withoutCompanyScope()
        ->where('company_id', $company->id)
        ->where('key', EmailTemplate::INVOICE_SENT)
        ->first();

    expect($template)->not->toBeNull()
        ->and($template->subject)->toBe('Your invoice {{invoice_number}}');
});

it('sends a test email and logs it', function () {
    Mail::fake();

    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->post('/portal/settings/email/test', ['email' => 'ops@acme.test'])->assertRedirect();

    Mail::assertSent(TestEmail::class, fn ($mail) => $mail->hasTo('ops@acme.test'));
    expect(EmailLog::where('company_id', $company->id)->count())->toBe(1);
});
