import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { formatMinor } from '@/lib/invoiceTotals';

interface OpenInvoice {
    id: number;
    number: string;
    customer_id: number;
    customer_name: string | null;
    due_date: string;
    balance: number;
    balance_display: string;
}

interface Props {
    customers: { id: number; name: string }[];
    accounts: { id: number; name: string; currency: string }[];
    methods: { value: string; label: string }[];
    openInvoices: OpenInvoice[];
    selectedInvoiceId: number | null;
    defaults: { date: string };
    currency: string;
}

function newKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `pay-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export default function PaymentForm({ customers, accounts, methods, openInvoices, selectedInvoiceId, defaults, currency }: Props) {
    const [idempotencyKey] = useState(newKey);
    const initial = openInvoices.find((i) => i.id === selectedInvoiceId) ?? null;

    const form = useForm({
        customer_id: (initial?.customer_id ?? '') as number | '',
        bank_account_id: (accounts[0]?.id ?? '') as number | '',
        date: defaults.date,
        amount: initial ? (initial.balance / 100).toFixed(2) : '',
        method: 'bank_transfer',
        reference: '',
        notes: '',
        send_receipt: true,
        idempotency_key: idempotencyKey,
        allocations: (initial ? [{ invoice_id: initial.id, amount: (initial.balance / 100).toFixed(2) }] : []) as { invoice_id: number; amount: string }[],
    });

    const customerInvoices = openInvoices.filter((i) => i.customer_id === form.data.customer_id);
    const allocatedMinor = form.data.allocations.reduce((sum, a) => sum + Math.round(Number(a.amount || 0) * 100), 0);
    const amountMinor = Math.round(Number(form.data.amount || 0) * 100);
    const unapplied = Math.max(0, amountMinor - allocatedMinor);

    const changeCustomer = (value: string) => {
        form.setData('customer_id', value === '' ? '' : Number(value));
        form.setData('allocations', []);
    };

    const setAllocation = (invoiceId: number, amount: string) => {
        const existing = form.data.allocations.filter((a) => a.invoice_id !== invoiceId);
        const next = amount === '' || Number(amount) === 0 ? existing : [...existing, { invoice_id: invoiceId, amount }];
        form.setData('allocations', next);
    };

    const autoAllocate = () => {
        let remaining = amountMinor;
        const allocations: { invoice_id: number; amount: string }[] = [];

        for (const invoice of customerInvoices) {
            if (remaining <= 0) break;
            const take = Math.min(invoice.balance, remaining);
            allocations.push({ invoice_id: invoice.id, amount: (take / 100).toFixed(2) });
            remaining -= take;
        }

        form.setData('allocations', allocations);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/portal/payments');
    };

    const allocationValue = (invoiceId: number): string =>
        form.data.allocations.find((a) => a.invoice_id === invoiceId)?.amount ?? '';

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Record payment</h1>
                <p className="text-sm text-slate-500 mt-0.5">Allocate the payment across open invoices. Any unallocated amount becomes customer credit.</p>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <Field label="Customer" required error={form.errors.customer_id}>
                        <select className={inputClass} value={form.data.customer_id} onChange={(e) => changeCustomer(e.target.value)}>
                            <option value="">Select a customer</option>
                            {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Account" required error={form.errors.bank_account_id}>
                        <select className={inputClass} value={form.data.bank_account_id} onChange={(e) => form.setData('bank_account_id', Number(e.target.value))}>
                            {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Date" required error={form.errors.date}>
                        <input type="date" className={inputClass} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                    </Field>
                    <Field label={`Amount (${currency})`} required error={form.errors.amount}>
                        <input type="number" min="0" step="0.01" className={inputClass} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                    </Field>
                    <Field label="Method" required error={form.errors.method}>
                        <select className={inputClass} value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                            {methods.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Reference" error={form.errors.reference}>
                        <input className={inputClass} value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                    </Field>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                        <h2 className="text-sm font-semibold text-slate-800">Allocate to invoices</h2>
                        <button type="button" onClick={autoAllocate} disabled={!form.data.customer_id} className="text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-40">
                            Auto-allocate
                        </button>
                    </div>
                    {!form.data.customer_id ? (
                        <p className="px-5 py-8 text-sm text-slate-500">Select a customer to see their open invoices.</p>
                    ) : customerInvoices.length === 0 ? (
                        <p className="px-5 py-8 text-sm text-slate-500">This customer has no open invoices. The payment will be recorded as credit.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                        <th className="px-5 py-3 font-medium">Invoice</th>
                                        <th className="px-5 py-3 font-medium">Due</th>
                                        <th className="px-5 py-3 font-medium text-right">Balance</th>
                                        <th className="px-5 py-3 font-medium text-right">Allocate</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {customerInvoices.map((invoice) => (
                                        <tr key={invoice.id}>
                                            <td className="px-5 py-3 text-slate-800">{invoice.number}</td>
                                            <td className="px-5 py-3 text-slate-600">{invoice.due_date}</td>
                                            <td className="px-5 py-3 text-right text-slate-600">{invoice.balance_display}</td>
                                            <td className="px-5 py-3 text-right">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={allocationValue(invoice.id)}
                                                    onChange={(e) => setAllocation(invoice.id, e.target.value)}
                                                    className="w-28 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-right text-sm"
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="px-5 py-3 border-t border-slate-100 text-sm text-slate-500">
                        Unapplied credit: <span className="font-medium text-slate-700">{formatMinor(unapplied, currency)}</span>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <Field label="Notes" error={form.errors.notes}>
                        <textarea className={inputClass} rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-3 text-sm text-slate-700">
                        <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked={form.data.send_receipt} onChange={(e) => form.setData('send_receipt', e.target.checked)} />
                        Email a receipt to the customer
                    </label>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Saving...' : 'Record payment'}
                    </button>
                    <Link href="/portal/payments" className="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</Link>
                </div>
            </form>
        </PortalLayout>
    );
}
