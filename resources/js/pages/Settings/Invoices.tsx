import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    settings: {
        invoice_prefix: string;
        invoice_number_padding: number;
        default_payment_terms_days: number;
        default_invoice_terms: string | null;
        invoice_footer: string | null;
    };
    paymentTerms: number[];
    nextNumber: string;
}

export default function InvoiceSettings({ settings, paymentTerms, nextNumber }: Props) {
    const form = useForm({
        invoice_prefix: settings.invoice_prefix,
        invoice_number_padding: settings.invoice_number_padding,
        default_payment_terms_days: settings.default_payment_terms_days,
        default_invoice_terms: settings.default_invoice_terms ?? '',
        invoice_footer: settings.invoice_footer ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch('/portal/settings/invoices');
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Invoice settings</h1>
                <p className="text-sm text-slate-500 mt-0.5">Numbering, terms and footer. Next number: {nextNumber}</p>
            </div>

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <Field label="Invoice prefix" required error={form.errors.invoice_prefix}>
                            <input className={inputClass} value={form.data.invoice_prefix} onChange={(e) => form.setData('invoice_prefix', e.target.value)} />
                        </Field>
                        <Field label="Number padding" required error={form.errors.invoice_number_padding}>
                            <input type="number" min={1} max={10} className={inputClass} value={form.data.invoice_number_padding} onChange={(e) => form.setData('invoice_number_padding', Number(e.target.value))} />
                        </Field>
                        <Field label="Default payment terms" required error={form.errors.default_payment_terms_days}>
                            <select className={inputClass} value={form.data.default_payment_terms_days} onChange={(e) => form.setData('default_payment_terms_days', Number(e.target.value))}>
                                {paymentTerms.map((days) => <option key={days} value={days}>{days === 0 ? 'Due on receipt' : `Net ${days}`}</option>)}
                            </select>
                        </Field>
                    </div>
                    <Field label="Default terms" error={form.errors.default_invoice_terms} hint="Pre-filled on new invoices.">
                        <textarea className={inputClass} rows={2} value={form.data.default_invoice_terms} onChange={(e) => form.setData('default_invoice_terms', e.target.value)} />
                    </Field>
                    <Field label="Invoice footer" error={form.errors.invoice_footer}>
                        <input className={inputClass} value={form.data.invoice_footer} onChange={(e) => form.setData('invoice_footer', e.target.value)} />
                    </Field>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {form.processing ? 'Saving...' : 'Save settings'}
                </button>
            </form>
        </PortalLayout>
    );
}
