import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Customer {
    id: number;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    tax_id: string | null;
    currency: string | null;
    payment_terms_days: number | null;
    notes: string | null;
    is_active: boolean;
    created_at: string;
}

interface InvoiceRow {
    id: number;
    number: string;
    issue_date: string;
    due_date: string;
    status_label: string;
    status_class: string;
    total_display: string;
    balance_display: string;
}

interface PaymentRow {
    id: number;
    date: string;
    amount_display: string;
    method_label: string;
    reference: string | null;
}

interface Props {
    customer: Customer;
    summary: { invoiced: string; paid: string; credited: string; outstanding: string; opening: string; credit_balance: string };
    invoices: InvoiceRow[];
    payments: PaymentRow[];
}

function Row({ label, value }: { label: string; value: string | number | null }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</dt>
            <dd className="mt-1 text-sm text-slate-800 whitespace-pre-line">{value ?? '—'}</dd>
        </div>
    );
}

export default function CustomerShow({ customer, summary, invoices, payments }: Props) {
    const openingForm = useForm({
        date: new Date().toISOString().slice(0, 10),
        amount: '',
        type: 'debit',
    });

    const saveOpening = (e: FormEvent) => {
        e.preventDefault();
        openingForm.post(`/portal/customers/${customer.id}/opening-balance`, { preserveScroll: true, onSuccess: () => openingForm.reset('amount') });
    };

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-xl font-bold text-slate-900">{customer.name}</h1>
                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${customer.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600'}`}>
                            {customer.is_active ? 'Active' : 'Archived'}
                        </span>
                    </div>
                    {customer.company_name && <p className="text-sm text-slate-500 mt-0.5">{customer.company_name}</p>}
                </div>
                <div className="flex gap-2">
                    <Link href={`/portal/reports/customers/${customer.id}/statement`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Statement
                    </Link>
                    <Link href="/portal/customers" className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Back
                    </Link>
                    <Link href={`/portal/customers/${customer.id}/edit`} className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Edit
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                {([
                    ['Invoiced', summary.invoiced],
                    ['Paid', summary.paid],
                    ['Credited', summary.credited],
                    ['Opening', summary.opening],
                    ['Outstanding', summary.outstanding],
                    ['Credit balance', summary.credit_balance],
                ] as const).map(([label, value]) => (
                    <div key={label} className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
                        <p className="mt-1 text-lg font-bold text-slate-900">{value}</p>
                    </div>
                ))}
            </div>

            <form onSubmit={saveOpening} className="mb-6 bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Opening balance date</label>
                    <input type="date" value={openingForm.data.date} onChange={(e) => openingForm.setData('date', e.target.value)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Amount</label>
                    <input type="number" min="0" step="0.01" value={openingForm.data.amount} onChange={(e) => openingForm.setData('amount', e.target.value)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                    {openingForm.errors.amount && <p className="mt-1 text-xs text-red-500">{openingForm.errors.amount}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Direction</label>
                    <select value={openingForm.data.type} onChange={(e) => openingForm.setData('type', e.target.value)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <option value="debit">Customer owes</option>
                        <option value="credit">Customer credit</option>
                    </select>
                </div>
                <button type="submit" disabled={openingForm.processing || !openingForm.data.amount} className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50">
                    Record opening balance
                </button>
            </form>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h2 className="text-sm font-semibold text-slate-800">Invoices</h2>
                        </div>
                        {invoices.length === 0 ? (
                            <p className="px-5 py-8 text-sm text-slate-500">No invoices yet.</p>
                        ) : (
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                        <th className="px-5 py-3 font-medium">Invoice</th>
                                        <th className="px-5 py-3 font-medium">Due</th>
                                        <th className="px-5 py-3 font-medium">Status</th>
                                        <th className="px-5 py-3 font-medium text-right">Total</th>
                                        <th className="px-5 py-3 font-medium text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {invoices.map((invoice) => (
                                        <tr key={invoice.id}>
                                            <td className="px-5 py-3">
                                                <Link href={`/portal/invoices/${invoice.id}`} className="font-medium text-indigo-600 hover:text-indigo-800">
                                                    {invoice.number}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3 text-slate-600">{invoice.due_date}</td>
                                            <td className="px-5 py-3">
                                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${invoice.status_class}`}>
                                                    {invoice.status_label}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 text-right text-slate-700">{invoice.total_display}</td>
                                            <td className="px-5 py-3 text-right font-medium text-slate-800">{invoice.balance_display}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h2 className="text-sm font-semibold text-slate-800">Payments</h2>
                        </div>
                        {payments.length === 0 ? (
                            <p className="px-5 py-8 text-sm text-slate-500">No payments yet.</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {payments.map((payment) => (
                                    <li key={payment.id} className="flex items-center justify-between px-5 py-3">
                                        <div>
                                            <Link href={`/portal/payments/${payment.id}`} className="text-sm font-medium text-slate-800 hover:text-indigo-600">
                                                {payment.date}
                                            </Link>
                                            <p className="text-xs text-slate-500">{payment.method_label}{payment.reference ? ` · ${payment.reference}` : ''}</p>
                                        </div>
                                        <span className="text-sm font-medium text-slate-800">{payment.amount_display}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Contact details</h2>
                    <dl className="space-y-4">
                        <Row label="Email" value={customer.email} />
                        <Row label="Phone" value={customer.phone} />
                        <Row label="Tax ID" value={customer.tax_id} />
                        <Row label="Payment terms" value={customer.payment_terms_days != null ? `Net ${customer.payment_terms_days}` : null} />
                        <Row label="Billing address" value={customer.billing_address} />
                        <Row label="Shipping address" value={customer.shipping_address} />
                        <Row label="Customer since" value={new Date(customer.created_at).toLocaleDateString()} />
                        {customer.notes && <Row label="Notes" value={customer.notes} />}
                    </dl>
                </div>
            </div>
        </PortalLayout>
    );
}
