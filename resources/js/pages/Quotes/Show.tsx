import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';

interface Props {
    quote: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        status_class: string;
        issue_date: string;
        valid_until: string | null;
        tax_inclusive: boolean;
        notes: string | null;
        terms: string | null;
        is_converted: boolean;
        converted_invoice: { id: number; number: string } | null;
        formatted: Record<string, string>;
        customer: { id: number; name: string; company_name: string | null; email: string | null; billing_address: string | null };
        items: { id: number; description: string; quantity: number; unit_price: string; tax_rate: number | null; line_total: string }[];
    };
    can: { update: boolean; delete: boolean; convert: boolean };
}

export default function QuoteShow({ quote, can }: Props) {
    const act = (url: string, method: 'post' | 'delete' = 'post', confirmMessage?: string) => {
        if (confirmMessage && !window.confirm(confirmMessage)) return;
        if (method === 'delete') router.delete(url);
        else router.post(url);
    };

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h1 className="text-xl font-bold text-slate-900">{quote.number}</h1>
                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${quote.status_class}`}>
                        {quote.status_label}
                    </span>
                </div>
                <div className="flex flex-wrap gap-2">
                    {can.update && (
                        <Link href={`/portal/quotes/${quote.id}/edit`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</Link>
                    )}
                    {quote.status === 'draft' && (
                        <button onClick={() => act(`/portal/quotes/${quote.id}/send`)} className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Mark as sent</button>
                    )}
                    {quote.status === 'sent' && (
                        <>
                            <button onClick={() => act(`/portal/quotes/${quote.id}/accept`)} className="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Mark accepted</button>
                            <button onClick={() => act(`/portal/quotes/${quote.id}/decline`)} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Decline</button>
                        </>
                    )}
                    {can.convert && !quote.is_converted && (
                        <button onClick={() => act(`/portal/quotes/${quote.id}/convert`, 'post', 'Convert this quote into a draft invoice?')} className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
                            Convert to invoice
                        </button>
                    )}
                    {can.delete && (
                        <button onClick={() => act(`/portal/quotes/${quote.id}`, 'delete', 'Delete this quote?')} className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Delete</button>
                    )}
                    <Link href="/portal/quotes" className="rounded-xl px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Back</Link>
                </div>
            </div>

            {quote.is_converted && quote.converted_invoice && (
                <div className="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                    Converted to invoice{' '}
                    <Link href={`/portal/invoices/${quote.converted_invoice.id}`} className="font-semibold underline">{quote.converted_invoice.number}</Link>.
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div className="flex justify-between mb-6">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Prepared for</p>
                            <p className="mt-1 font-semibold text-slate-800">{quote.customer.name}</p>
                            {quote.customer.company_name && <p className="text-sm text-slate-500">{quote.customer.company_name}</p>}
                            {quote.customer.email && <p className="text-sm text-slate-500">{quote.customer.email}</p>}
                        </div>
                        <div className="text-right">
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Issued</p>
                            <p className="text-sm text-slate-700">{quote.issue_date}</p>
                            {quote.valid_until && <>
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-400 mt-3">Valid until</p>
                                <p className="text-sm text-slate-700">{quote.valid_until}</p>
                            </>}
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
                            {quote.items.map((item) => (
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

                    {(quote.notes || quote.terms) && (
                        <div className="mt-6 pt-5 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-5 text-sm">
                            {quote.notes && <div><p className="text-xs font-medium uppercase tracking-wide text-slate-400">Notes</p><p className="mt-1 text-slate-600 whitespace-pre-line">{quote.notes}</p></div>}
                            {quote.terms && <div><p className="text-xs font-medium uppercase tracking-wide text-slate-400">Terms</p><p className="mt-1 text-slate-600 whitespace-pre-line">{quote.terms}</p></div>}
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Summary</h2>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between"><dt className="text-slate-500">Subtotal</dt><dd className="font-medium text-slate-800">{quote.formatted.subtotal}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Discount</dt><dd className="font-medium text-slate-800">-{quote.formatted.discount}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">{quote.tax_inclusive ? 'Tax (included)' : 'Tax'}</dt><dd className="font-medium text-slate-800">{quote.formatted.tax}</dd></div>
                        <div className="flex justify-between border-t border-slate-100 pt-3"><dt className="font-semibold text-slate-700">Total</dt><dd className="font-bold text-slate-900">{quote.formatted.total}</dd></div>
                    </dl>
                </div>
            </div>
        </PortalLayout>
    );
}
