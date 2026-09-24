import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    settings: {
        online_payments_enabled: boolean;
        has_secret: boolean;
        has_webhook_secret: boolean;
        deposit_account_id: number | null;
    };
    accounts: { id: number; name: string }[];
    webhookUrl: string;
}

export default function PaymentSettings({ settings, accounts, webhookUrl }: Props) {
    const form = useForm({
        online_payments_enabled: settings.online_payments_enabled,
        stripe_secret_key: '',
        stripe_webhook_secret: '',
        stripe_deposit_account_id: (settings.deposit_account_id ?? '') as number | '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch('/portal/settings/payments', { preserveScroll: true, onSuccess: () => {
            form.setData('stripe_secret_key', '');
            form.setData('stripe_webhook_secret', '');
        } });
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Online payments</h1>
                <p className="text-sm text-slate-500 mt-0.5">Let customers pay invoices online via Stripe. Payments are recorded automatically against the invoice.</p>
            </div>

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <label className="flex items-center gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            checked={form.data.online_payments_enabled}
                            onChange={(e) => form.setData('online_payments_enabled', e.target.checked)}
                        />
                        Enable online payments (Stripe) on public invoices
                    </label>

                    <Field
                        label="Stripe secret key"
                        error={form.errors.stripe_secret_key}
                        hint={settings.has_secret ? 'A key is saved. Leave blank to keep it.' : 'Starts with sk_live_ or sk_test_.'}
                    >
                        <input
                            type="password"
                            autoComplete="off"
                            className={inputClass}
                            value={form.data.stripe_secret_key}
                            onChange={(e) => form.setData('stripe_secret_key', e.target.value)}
                            placeholder={settings.has_secret ? '••••••••' : 'sk_live_…'}
                        />
                    </Field>

                    <Field
                        label="Stripe webhook signing secret"
                        error={form.errors.stripe_webhook_secret}
                        hint={settings.has_webhook_secret ? 'A secret is saved. Leave blank to keep it.' : 'Starts with whsec_.'}
                    >
                        <input
                            type="password"
                            autoComplete="off"
                            className={inputClass}
                            value={form.data.stripe_webhook_secret}
                            onChange={(e) => form.setData('stripe_webhook_secret', e.target.value)}
                            placeholder={settings.has_webhook_secret ? '••••••••' : 'whsec_…'}
                        />
                    </Field>

                    <Field label="Deposit account" error={form.errors.stripe_deposit_account_id} hint="The bank/cash account online payments land in.">
                        <select
                            className={inputClass}
                            value={form.data.stripe_deposit_account_id}
                            onChange={(e) => form.setData('stripe_deposit_account_id', e.target.value === '' ? '' : Number(e.target.value))}
                        >
                            <option value="">Select an account</option>
                            {accounts.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}
                        </select>
                    </Field>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-2">Webhook endpoint</h2>
                    <p className="text-xs text-slate-500 mb-2">Add this URL to your Stripe dashboard for the <code>checkout.session.completed</code> event.</p>
                    <code className="block break-all rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-700">{webhookUrl}</code>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {form.processing ? 'Saving...' : 'Save settings'}
                </button>
            </form>
        </PortalLayout>
    );
}
