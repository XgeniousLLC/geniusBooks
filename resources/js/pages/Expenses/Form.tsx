import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    expense: {
        id: number;
        date: string;
        amount: string;
        tax_amount: string | number;
        expense_category_id: number | null;
        vendor_id: number | null;
        bank_account_id: number | null;
        description: string;
        reference: string | null;
        notes: string | null;
        is_recurring: boolean;
        recurrence_interval: string | null;
        attachment_name: string | null;
    } | null;
    categories: { id: number; name: string }[];
    vendors: { id: number; name: string }[];
    accounts: { id: number; name: string }[];
    intervals: string[];
    currency: string;
    defaults: { date: string };
}

export default function ExpenseForm({ expense, categories, vendors, accounts, intervals, currency, defaults }: Props) {
    const editing = Boolean(expense);

    const form = useForm({
        date: expense?.date ?? defaults.date,
        amount: expense?.amount ?? '',
        tax_amount: expense?.tax_amount != null ? String(expense.tax_amount) : '',
        expense_category_id: (expense?.expense_category_id ?? '') as number | '',
        vendor_id: (expense?.vendor_id ?? '') as number | '',
        bank_account_id: (expense?.bank_account_id ?? (accounts[0]?.id ?? '')) as number | '',
        description: expense?.description ?? '',
        reference: expense?.reference ?? '',
        notes: expense?.notes ?? '',
        attachment: null as File | null,
        is_recurring: expense?.is_recurring ?? false,
        recurrence_interval: expense?.recurrence_interval ?? 'monthly',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing && expense) {
            // POST + _method so PHP parses the multipart body (attachment).
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/portal/expenses/${expense.id}`, { forceFormData: true });
        } else {
            form.post('/portal/expenses', { forceFormData: true });
        }
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{editing ? 'Edit expense' : 'New expense'}</h1>
                <p className="text-sm text-slate-500 mt-0.5">Recorded against the selected account and posted to the ledger.</p>
            </div>

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <Field label="Date" required error={form.errors.date}>
                            <input type="date" className={inputClass} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                        </Field>
                        <Field label={`Amount (${currency})`} required error={form.errors.amount}>
                            <input type="number" min="0" step="0.01" className={inputClass} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                        </Field>
                        <Field label="Tax included (optional)" error={form.errors.tax_amount}>
                            <input type="number" min="0" step="0.01" className={inputClass} value={form.data.tax_amount} onChange={(e) => form.setData('tax_amount', e.target.value)} />
                        </Field>
                    </div>

                    <Field label="Description" required error={form.errors.description}>
                        <input className={inputClass} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <Field label="Category" error={form.errors.expense_category_id}>
                            <select className={inputClass} value={form.data.expense_category_id} onChange={(e) => form.setData('expense_category_id', e.target.value === '' ? '' : Number(e.target.value))}>
                                <option value="">Uncategorised</option>
                                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Vendor" error={form.errors.vendor_id}>
                            <select className={inputClass} value={form.data.vendor_id} onChange={(e) => form.setData('vendor_id', e.target.value === '' ? '' : Number(e.target.value))}>
                                <option value="">None</option>
                                {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Paid from" error={form.errors.bank_account_id}>
                            <select className={inputClass} value={form.data.bank_account_id} onChange={(e) => form.setData('bank_account_id', e.target.value === '' ? '' : Number(e.target.value))}>
                                <option value="">Not from an account</option>
                                {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                        </Field>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Reference" error={form.errors.reference}>
                            <input className={inputClass} value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                        </Field>
                        <Field label="Attachment" hint={expense?.attachment_name ? `Current: ${expense.attachment_name}` : 'PDF, JPG or PNG up to 10MB'} error={form.errors.attachment}>
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)}
                                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                        </Field>
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <textarea className={inputClass} rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>

                    <div className="border-t border-slate-100 pt-5 space-y-4">
                        <label className="flex items-center gap-3 text-sm text-slate-700">
                            <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked={form.data.is_recurring} onChange={(e) => form.setData('is_recurring', e.target.checked)} />
                            Repeat this expense automatically
                        </label>
                        {form.data.is_recurring && (
                            <Field label="Interval" error={form.errors.recurrence_interval}>
                                <select className={inputClass} value={form.data.recurrence_interval} onChange={(e) => form.setData('recurrence_interval', e.target.value)}>
                                    {intervals.map((interval) => <option key={interval} value={interval}>{interval}</option>)}
                                </select>
                            </Field>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Saving...' : editing ? 'Save changes' : 'Record expense'}
                    </button>
                    <Link href="/portal/expenses" className="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</Link>
                </div>
            </form>
        </PortalLayout>
    );
}
