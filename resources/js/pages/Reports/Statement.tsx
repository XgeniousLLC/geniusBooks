import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface StatementRow {
    date: string;
    type: string;
    reference: string;
    debit_display: string;
    credit_display: string;
    balance_display: string;
}

interface Props {
    statement: {
        customer: { id: number; name: string; email: string | null; company_name: string | null };
        period: { from: string; to: string };
        opening_display: string;
        total_debit_display: string;
        total_credit_display: string;
        closing_display: string;
        rows: StatementRow[];
    };
}

export default function CustomerStatement({ statement }: Props) {
    const [from, setFrom] = useState(statement.period.from);
    const [to, setTo] = useState(statement.period.to);
    const basePath = `/portal/reports/customers/${statement.customer.id}/statement`;

    const apply = (e: FormEvent) => {
        e.preventDefault();
        router.get(basePath, { from, to }, { preserveState: true, preserveScroll: true });
    };

    const exportUrl = (format: string) => `${basePath}?from=${from}&to=${to}&format=${format}`;

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Statement — {statement.customer.name}</h1>
                    <p className="text-sm text-slate-500 mt-0.5">{statement.period.from} – {statement.period.to}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a href={exportUrl('csv')} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href={exportUrl('pdf')} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">PDF</a>
                    <button onClick={() => router.post(`${basePath}/email`, { from, to })} className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Email statement
                    </button>
                    <Link href={`/portal/customers/${statement.customer.id}`} className="rounded-xl px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Customer</Link>
                </div>
            </div>

            <form onSubmit={apply} className="mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">From</label>
                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">To</label>
                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Apply</button>
            </form>

            <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                            <th className="px-5 py-3 font-medium">Date</th>
                            <th className="px-5 py-3 font-medium">Type</th>
                            <th className="px-5 py-3 font-medium">Reference</th>
                            <th className="px-5 py-3 font-medium text-right">Debit</th>
                            <th className="px-5 py-3 font-medium text-right">Credit</th>
                            <th className="px-5 py-3 font-medium text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        <tr className="bg-slate-50">
                            <td className="px-5 py-3 text-slate-500" colSpan={5}>Opening balance</td>
                            <td className="px-5 py-3 text-right font-medium text-slate-700">{statement.opening_display}</td>
                        </tr>
                        {statement.rows.map((row, index) => (
                            <tr key={index}>
                                <td className="px-5 py-3 text-slate-600">{row.date}</td>
                                <td className="px-5 py-3 text-slate-700">{row.type}</td>
                                <td className="px-5 py-3 text-slate-500">{row.reference}</td>
                                <td className="px-5 py-3 text-right text-slate-700">{row.debit_display}</td>
                                <td className="px-5 py-3 text-right text-slate-700">{row.credit_display}</td>
                                <td className="px-5 py-3 text-right font-medium text-slate-800">{row.balance_display}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-slate-100">
                            <td className="px-5 py-3 font-semibold text-slate-700" colSpan={3}>Totals</td>
                            <td className="px-5 py-3 text-right font-medium text-slate-800">{statement.total_debit_display}</td>
                            <td className="px-5 py-3 text-right font-medium text-slate-800">{statement.total_credit_display}</td>
                            <td className="px-5 py-3 text-right font-bold text-slate-900">{statement.closing_display}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </PortalLayout>
    );
}
