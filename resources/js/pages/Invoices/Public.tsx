interface Props {
    invoice: {
        number: string;
        status_label: string;
        status_class: string;
        issue_date: string;
        due_date: string;
        tax_inclusive: boolean;
        notes: string | null;
        terms: string | null;
        formatted: Record<string, string>;
        customer: { name: string; company_name: string | null; email: string | null; billing_address: string | null };
        items: { id: number; description: string; quantity: number; unit_price: string; tax_rate: number | null; line_total: string }[];
    };
    company: {
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        payment_instructions: string | null;
        invoice_footer: string | null;
    };
    pdfUrl: string;
    canPay: boolean;
    payUrl: string | null;
}

export default function PublicInvoice({ invoice, company, pdfUrl, canPay, payUrl }: Props) {
    return (
        <div className="min-h-screen bg-slate-100 py-10 px-4">
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">{company.name}</h1>
                        {company.email && <p className="text-sm text-slate-500">{company.email}</p>}
                    </div>
                    <div className="flex items-center gap-2">
                        {canPay && payUrl && (
                            <a
                                href={payUrl}
                                className="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
                            >
                                Pay {invoice.formatted.balance}
                            </a>
                        )}
                        <a
                            href={pdfUrl}
                            className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                        >
                            Download PDF
                        </a>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
                    <div className="flex justify-between mb-8">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Invoice</p>
                            <p className="text-lg font-bold text-slate-900">{invoice.number}</p>
                            <span className={`mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${invoice.status_class}`}>
                                {invoice.status_label}
                            </span>
                        </div>
                        <div className="text-right text-sm">
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Issued</p>
                            <p className="text-slate-700">{invoice.issue_date}</p>
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400 mt-2">Due</p>
                            <p className="text-slate-700">{invoice.due_date}</p>
                        </div>
                    </div>

                    <div className="mb-8">
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Billed to</p>
                        <p className="mt-1 font-semibold text-slate-800">{invoice.customer.name}</p>
                        {invoice.customer.company_name && <p className="text-sm text-slate-500">{invoice.customer.company_name}</p>}
                        {invoice.customer.billing_address && <p className="text-sm text-slate-500 whitespace-pre-line">{invoice.customer.billing_address}</p>}
                    </div>

                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                <th className="py-2 font-medium">Description</th>
                                <th className="py-2 font-medium text-right">Qty</th>
                                <th className="py-2 font-medium text-right">Unit price</th>
                                <th className="py-2 font-medium text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {invoice.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="py-3 text-slate-800">{item.description}</td>
                                    <td className="py-3 text-right text-slate-600">{item.quantity}</td>
                                    <td className="py-3 text-right text-slate-600">{item.unit_price}</td>
                                    <td className="py-3 text-right font-medium text-slate-800">{item.line_total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <dl className="mt-6 ml-auto w-full sm:w-64 space-y-2 text-sm">
                        <div className="flex justify-between"><dt className="text-slate-500">Subtotal</dt><dd className="font-medium text-slate-800">{invoice.formatted.subtotal}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Discount</dt><dd className="font-medium text-slate-800">-{invoice.formatted.discount}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">{invoice.tax_inclusive ? 'Tax (included)' : 'Tax'}</dt><dd className="font-medium text-slate-800">{invoice.formatted.tax}</dd></div>
                        <div className="flex justify-between border-t border-slate-100 pt-2"><dt className="font-semibold text-slate-700">Total</dt><dd className="font-bold text-slate-900">{invoice.formatted.total}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Paid</dt><dd className="font-medium text-slate-800">{invoice.formatted.paid}</dd></div>
                        <div className="flex justify-between border-t border-slate-100 pt-2"><dt className="font-semibold text-slate-700">Balance due</dt><dd className="font-bold text-slate-900">{invoice.formatted.balance}</dd></div>
                    </dl>

                    {company.payment_instructions && (
                        <div className="mt-8 rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Payment instructions</p>
                            <p className="mt-1 text-sm text-slate-700 whitespace-pre-line">{company.payment_instructions}</p>
                        </div>
                    )}

                    {(invoice.notes || invoice.terms) && (
                        <div className="mt-6 text-sm text-slate-500 space-y-2">
                            {invoice.notes && <p className="whitespace-pre-line">{invoice.notes}</p>}
                            {invoice.terms && <p className="whitespace-pre-line">{invoice.terms}</p>}
                        </div>
                    )}
                </div>

                {company.invoice_footer && (
                    <p className="text-center text-xs text-slate-400 mt-6">{company.invoice_footer}</p>
                )}
            </div>
        </div>
    );
}
