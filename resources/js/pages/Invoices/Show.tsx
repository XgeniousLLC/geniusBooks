import PortalLayout from '@/layouts/PortalLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    invoice: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        status_class: string;
        issue_date: string;
        due_date: string;
        tax_inclusive: boolean;
        notes: string | null;
        terms: string | null;
        balance: number;
        formatted: Record<string, string>;
        customer: {
            id: number;
            name: string;
            company_name: string | null;
            email: string | null;
            billing_address: string | null;
        };
        items: { id: number; description: string; quantity: number; unit_price: string; tax_rate: number | null; line_total: string }[];
    };
    accounts: { id: number; name: string }[];
    can: { update: boolean; delete: boolean; cancel: boolean; send: boolean; duplicate: boolean; credit_note: boolean };
}

export default function InvoiceShow({ invoice, accounts, can }: Props) {
    const [showCredit, setShowCredit] = useState(false);

    const creditForm = useForm({
        issue_date: new Date().toISOString().slice(0, 10),
        amount: '',
        reason: '',
        bank_account_id: '',
    });

    const act = (url: string, method: 'post' | 'delete' = 'post', confirmMessage?: string) => {
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }
        if (method === 'delete') {
            router.delete(url);
        } else {
            router.post(url);
        }
    };

    const issueCredit = () => {
        creditForm.post(`/portal/invoices/${invoice.id}/credit-notes`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowCredit(false);
                creditForm.reset();
            },
        });
    };

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h1 className="text-xl font-bold text-slate-900">{invoice.number}</h1>
                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${invoice.status_class}`}>
                        {invoice.status_label}
                    </span>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a href={`/portal/invoices/${invoice.id}/pdf`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Download PDF
                    </a>
                    <button onClick={() => act(`/portal/invoices/${invoice.id}/email`)} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Email invoice
                    </button>
                    {invoice.balance > 0 && invoice.status !== 'cancelled' && (
                        <Link href={`/portal/payments/create?invoice=${invoice.id}`} className="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                            Record payment
                        </Link>
                    )}
                    {can.credit_note && (
                        <button onClick={() => setShowCredit((v) => !v)} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Credit note
                        </button>
                    )}
                    {can.update && (
                        <Link href={`/portal/invoices/${invoice.id}/edit`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Edit
                        </Link>
                    )}
                    {can.send && (
                        <button onClick={() => act(`/portal/invoices/${invoice.id}/send`)} className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Mark as sent
                        </button>
                    )}
                    {can.duplicate && (
                        <button onClick={() => act(`/portal/invoices/${invoice.id}/duplicate`)} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Duplicate
                        </button>
                    )}
                    {can.cancel && invoice.status !== 'cancelled' && (
                        <button onClick={() => act(`/portal/invoices/${invoice.id}/cancel`, 'post', 'Cancel this invoice?')} className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Cancel
                        </button>
                    )}
                    {can.delete && (
                        <button onClick={() => act(`/portal/invoices/${invoice.id}`, 'delete', 'Delete this draft invoice?')} className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Delete
                        </button>
                    )}
                    <Link href="/portal/invoices" className="rounded-xl px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">
                        Back
                    </Link>
                </div>
            </div>

            {showCredit && (
                <div className="mb-6 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Issue credit note</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1.5">Date</label>
                            <input type="date" value={creditForm.data.issue_date} onChange={(e) => creditForm.setData('issue_date', e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1.5">Amount</label>
                            <input type="number" min="0" step="0.01" value={creditForm.data.amount} onChange={(e) => creditForm.setData('amount', e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                            {creditForm.errors.amount && <p className="mt-1 text-xs text-red-500">{creditForm.errors.amount}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1.5">Reason</label>
                            <input value={creditForm.data.reason} onChange={(e) => creditForm.setData('reason', e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1.5">Refund from (optional)</label>
                            <select value={creditForm.data.bank_account_id} onChange={(e) => creditForm.setData('bank_account_id', e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                <option value="">Apply to balance only</option>
                                {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                        </div>
                    </div>
                    <button onClick={issueCredit} disabled={creditForm.processing || !creditForm.data.amount} className="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {creditForm.processing ? 'Issuing...' : 'Issue credit note'}
                    </button>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div className="flex justify-between mb-6">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Billed to</p>
                            <p className="mt-1 font-semibold text-slate-800">{invoice.customer.name}</p>
                            {invoice.customer.company_name && <p className="text-sm text-slate-500">{invoice.customer.company_name}</p>}
                            {invoice.customer.email && <p className="text-sm text-slate-500">{invoice.customer.email}</p>}
                            {invoice.customer.billing_address && <p className="text-sm text-slate-500 whitespace-pre-line mt-1">{invoice.customer.billing_address}</p>}
                        </div>
                        <div className="text-right">
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Issue date</p>
                            <p className="text-sm text-slate-700">{invoice.issue_date}</p>
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400 mt-3">Due date</p>
                            <p className="text-sm text-slate-700">{invoice.due_date}</p>
                        </div>
                    </div>

                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                <th className="py-2 font-medium">Description</th>
                                <th className="py-2 font-medium text-right">Qty</th>
                                <th className="py-2 font-medium text-right">Unit price</th>
                                <th className="py-2 font-medium text-right">Tax</th>
                                <th className="py-2 font-medium text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {invoice.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="py-3 text-slate-800">{item.description}</td>
                                    <td className="py-3 text-right text-slate-600">{item.quantity}</td>
                                    <td className="py-3 text-right text-slate-600">{item.unit_price}</td>
                                    <td className="py-3 text-right text-slate-600">{item.tax_rate != null ? `${item.tax_rate}%` : '—'}</td>
                                    <td className="py-3 text-right font-medium text-slate-800">{item.line_total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {(invoice.notes || invoice.terms) && (
                        <div className="mt-6 pt-5 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-5 text-sm">
                            {invoice.notes && (
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Notes</p>
                                    <p className="mt-1 text-slate-600 whitespace-pre-line">{invoice.notes}</p>
                                </div>
                            )}
                            {invoice.terms && (
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Terms</p>
                                    <p className="mt-1 text-slate-600 whitespace-pre-line">{invoice.terms}</p>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Summary</h2>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between"><dt className="text-slate-500">Subtotal</dt><dd className="font-medium text-slate-800">{invoice.formatted.subtotal}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Discount</dt><dd className="font-medium text-slate-800">-{invoice.formatted.discount}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">{invoice.tax_inclusive ? 'Tax (included)' : 'Tax'}</dt><dd className="font-medium text-slate-800">{invoice.formatted.tax}</dd></div>
                        <div className="flex justify-between border-t border-slate-100 pt-3"><dt className="font-semibold text-slate-700">Total</dt><dd className="font-bold text-slate-900">{invoice.formatted.total}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Paid</dt><dd className="font-medium text-slate-800">{invoice.formatted.paid}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Credits</dt><dd className="font-medium text-slate-800">-{invoice.formatted.credit}</dd></div>
                        <div className="flex justify-between border-t border-slate-100 pt-3"><dt className="font-semibold text-slate-700">Balance due</dt><dd className="font-bold text-slate-900">{invoice.formatted.balance}</dd></div>
                    </dl>
                </div>
            </div>
        </PortalLayout>
    );
}
