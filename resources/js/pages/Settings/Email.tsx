import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Settings {
    email_from_name: string | null;
    email_reply_to: string | null;
    payment_instructions: string | null;
    invoice_footer: string | null;
    reminders_enabled: boolean;
    reminder_days_before: number;
    reminder_days_overdue: number;
    notify_invoice_sent: boolean;
    notify_payment_received: boolean;
    notify_invoice_due: boolean;
    notify_invoice_overdue: boolean;
    sms_notifications_enabled: boolean;
}

interface Template {
    key: string;
    label: string;
    subject: string;
    body: string;
}

interface Log {
    id: number;
    to: string;
    subject: string;
    status: string;
    created_at: string;
}

interface Props {
    settings: Settings;
    templates: Template[];
    logs: Log[];
    variables: string[];
    smsDriver: string;
}

export default function EmailSettings({ settings, templates, logs, variables, smsDriver }: Props) {
    const settingsForm = useForm({
        email_from_name: settings.email_from_name ?? '',
        email_reply_to: settings.email_reply_to ?? '',
        payment_instructions: settings.payment_instructions ?? '',
        invoice_footer: settings.invoice_footer ?? '',
        reminders_enabled: settings.reminders_enabled,
        reminder_days_before: settings.reminder_days_before,
        reminder_days_overdue: settings.reminder_days_overdue,
        notify_invoice_sent: settings.notify_invoice_sent,
        notify_payment_received: settings.notify_payment_received,
        notify_invoice_due: settings.notify_invoice_due,
        notify_invoice_overdue: settings.notify_invoice_overdue,
        sms_notifications_enabled: settings.sms_notifications_enabled,
    });

    const testForm = useForm({ email: '' });
    const smsForm = useForm({ phone: '' });

    const saveSettings = (e: FormEvent) => {
        e.preventDefault();
        settingsForm.patch('/portal/settings/email', { preserveScroll: true });
    };

    const sendTest = (e: FormEvent) => {
        e.preventDefault();
        testForm.post('/portal/settings/email/test', { preserveScroll: true, onSuccess: () => testForm.reset() });
    };

    const sendTestSms = (e: FormEvent) => {
        e.preventDefault();
        smsForm.post('/portal/settings/email/sms-test', { preserveScroll: true, onSuccess: () => smsForm.reset() });
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Email &amp; invoice delivery</h1>
                <p className="text-sm text-slate-500 mt-0.5">Sender identity, invoice document details, reminders and templates.</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <form onSubmit={saveSettings} className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                        <h2 className="text-sm font-semibold text-slate-800">Sender</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <Field label="From name" error={settingsForm.errors.email_from_name}>
                                <input className={inputClass} value={settingsForm.data.email_from_name} onChange={(e) => settingsForm.setData('email_from_name', e.target.value)} />
                            </Field>
                            <Field label="Reply-to address" error={settingsForm.errors.email_reply_to}>
                                <input type="email" className={inputClass} value={settingsForm.data.email_reply_to} onChange={(e) => settingsForm.setData('email_reply_to', e.target.value)} />
                            </Field>
                        </div>

                        <h2 className="text-sm font-semibold text-slate-800 pt-2">Invoice document</h2>
                        <Field label="Payment instructions" hint="Shown on the invoice PDF (bank details, etc.)." error={settingsForm.errors.payment_instructions}>
                            <textarea className={inputClass} rows={3} value={settingsForm.data.payment_instructions} onChange={(e) => settingsForm.setData('payment_instructions', e.target.value)} />
                        </Field>
                        <Field label="Invoice footer" error={settingsForm.errors.invoice_footer}>
                            <input className={inputClass} value={settingsForm.data.invoice_footer} onChange={(e) => settingsForm.setData('invoice_footer', e.target.value)} />
                        </Field>

                        <h2 className="text-sm font-semibold text-slate-800 pt-2">Reminders</h2>
                        <label className="flex items-center gap-3 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                checked={settingsForm.data.reminders_enabled}
                                onChange={(e) => settingsForm.setData('reminders_enabled', e.target.checked)}
                            />
                            Send automatic due and overdue reminders
                        </label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <Field label="Days before due" error={settingsForm.errors.reminder_days_before}>
                                <input type="number" min="0" max="60" className={inputClass} value={settingsForm.data.reminder_days_before} onChange={(e) => settingsForm.setData('reminder_days_before', Number(e.target.value))} />
                            </Field>
                            <Field label="Days after due (overdue)" error={settingsForm.errors.reminder_days_overdue}>
                                <input type="number" min="0" max="60" className={inputClass} value={settingsForm.data.reminder_days_overdue} onChange={(e) => settingsForm.setData('reminder_days_overdue', Number(e.target.value))} />
                            </Field>
                        </div>

                        <h2 className="text-sm font-semibold text-slate-800 pt-2">Email notifications</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {([
                                ['notify_invoice_sent', 'Invoice sent'],
                                ['notify_payment_received', 'Payment received'],
                                ['notify_invoice_due', 'Invoice due soon'],
                                ['notify_invoice_overdue', 'Invoice overdue'],
                                ['sms_notifications_enabled', 'Also send SMS (customers with a phone number)'],
                            ] as const).map(([key, label]) => (
                                <label key={key} className="flex items-center gap-3 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        checked={settingsForm.data[key]}
                                        onChange={(e) => settingsForm.setData(key, e.target.checked)}
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>

                        <button type="submit" disabled={settingsForm.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                            {settingsForm.processing ? 'Saving...' : 'Save settings'}
                        </button>
                    </form>

                    <div className="space-y-4">
                        <h2 className="text-sm font-semibold text-slate-800">Email templates</h2>
                        <p className="text-xs text-slate-500">
                            Variables: {variables.map((v) => `{{${v}}}`).join(', ')}
                        </p>
                        {templates.map((template) => (
                            <TemplateEditor key={template.key} template={template} />
                        ))}
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h2 className="text-sm font-semibold text-slate-800 mb-4">Send a test email</h2>
                        <form onSubmit={sendTest} className="space-y-3">
                            <input
                                type="email"
                                required
                                placeholder="you@example.com"
                                className={inputClass}
                                value={testForm.data.email}
                                onChange={(e) => testForm.setData('email', e.target.value)}
                            />
                            {testForm.errors.email && <p className="text-xs text-red-500">{testForm.errors.email}</p>}
                            <button type="submit" disabled={testForm.processing} className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                                {testForm.processing ? 'Sending...' : 'Send test email'}
                            </button>
                        </form>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h2 className="text-sm font-semibold text-slate-800 mb-1">Send a test SMS</h2>
                        <p className="text-xs text-slate-500 mb-4">Gateway driver: <span className="font-mono">{smsDriver}</span></p>
                        <form onSubmit={sendTestSms} className="space-y-3">
                            <input
                                type="tel"
                                required
                                placeholder="+1234567890"
                                className={inputClass}
                                value={smsForm.data.phone}
                                onChange={(e) => smsForm.setData('phone', e.target.value)}
                            />
                            {smsForm.errors.phone && <p className="text-xs text-red-500">{smsForm.errors.phone}</p>}
                            <button type="submit" disabled={smsForm.processing} className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                                {smsForm.processing ? 'Sending...' : 'Send test SMS'}
                            </button>
                        </form>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h2 className="text-sm font-semibold text-slate-800 mb-3">Recent sends</h2>
                        {logs.length === 0 ? (
                            <p className="text-sm text-slate-500">No emails sent yet.</p>
                        ) : (
                            <ul className="space-y-3">
                                {logs.map((log) => (
                                    <li key={log.id} className="text-xs">
                                        <p className="font-medium text-slate-700 truncate">{log.subject}</p>
                                        <p className="text-slate-500 truncate">
                                            {log.to} · <span className={log.status === 'failed' ? 'text-red-500' : 'text-slate-500'}>{log.status}</span>
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}

function TemplateEditor({ template }: { template: Template }) {
    const form = useForm({ key: template.key, subject: template.subject, body: template.body });

    const save = (e: FormEvent) => {
        e.preventDefault();
        form.post('/portal/settings/email/templates', { preserveScroll: true });
    };

    return (
        <form onSubmit={save} className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold text-slate-700">{template.label}</h3>
                <button type="submit" disabled={form.processing} className="text-xs font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50">
                    {form.processing ? 'Saving...' : 'Save'}
                </button>
            </div>
            <input className={inputClass} value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
            {form.errors.subject && <p className="text-xs text-red-500">{form.errors.subject}</p>}
            <textarea className={inputClass} rows={5} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
            {form.errors.body && <p className="text-xs text-red-500">{form.errors.body}</p>}
        </form>
    );
}
