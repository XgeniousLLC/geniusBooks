import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface Props {
    accounts: { id: number; name: string }[];
    ledgerAccounts: { id: number; code: string | null; name: string }[];
    selected: { account_type: string; account_id: number | null };
    period: { from: string; to: string };
    ledger: {
        title: string;
        account_name: string;
        opening_display: string;
        total_debit_display: string;
        total_credit_display: string;
        closing_display: string;
        rows: { date: string; description: string; type: string; debit_display: string; credit_display: string; balance_display: string }[];
    } | null;
}

export default function GeneralLedger({ accounts, ledgerAccounts, selected, period, ledger }: Props) {
    const [accountType, setAccountType] = useState(selected.account_type);
    const [accountId, setAccountId] = useState(selected.account_id ? String(selected.account_id) : '');
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);

    const options: Array<{ id: number; name: string; code?: string | null }> = accountType === 'bank' ? accounts : ledgerAccounts;

    const apply = (e: FormEvent) => {
        e.preventDefault();
        router.get('/portal/reports/general-ledger', { account_type: accountType, account_id: accountId, from, to }, { preserveState: true });
    };

    const exportUrl = (format: string) =>
        `/portal/reports/general-ledger?account_type=${accountType}&account_id=${accountId}&from=${from}&to=${to}&format=${format}`;

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">General Ledger</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Opening balance, entries and closing balance for any account.</p>
                </div>
                {ledger && (
                    <div className="flex gap-2">
                        <a href={exportUrl('csv')} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">CSV</a>
                        <a href={exportUrl('pdf')} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">PDF</a>
                    </div>
                )}
            </div>

            <form onSubmit={apply} className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-40">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Account type</label>
                    <select value={accountType} onChange={(e) => { setAccountType(e.target.value); setAccountId(''); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="bank">Bank / cash</option>
                        <option value="ledger">Chart account</option>
                    </select>
                </div>
                <div className="min-w-[220px]">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Account</label>
                    <select value={accountId} onChange={(e) => setAccountId(e.target.value)} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Select an account…</option>
                        {options.map((option) => (
                            <option key={option.id} value={option.id}>{option.code ? `${option.code} · ` : ''}{option.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">From</label>
                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">To</label>
                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <button type="submit" disabled={!accountId} className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50">View</button>
                <Link href="/portal/reports" className="text-sm font-medium text-slate-500 hover:text-slate-700">All reports</Link>
            </form>

            {!ledger ? (
                <p className="text-sm text-slate-500">Choose an account and date range to view its ledger.</p>
            ) : (
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold text-slate-800">{ledger.title}</h2>
                        <span className="text-xs text-slate-500">{period.from} – {period.to}</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                    <th className="px-5 py-3 font-medium">Date</th>
                                    <th className="px-5 py-3 font-medium">Description</th>
                                    <th className="px-5 py-3 font-medium">Type</th>
                                    <th className="px-5 py-3 font-medium text-right">Debit</th>
                                    <th className="px-5 py-3 font-medium text-right">Credit</th>
                                    <th className="px-5 py-3 font-medium text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                <tr className="bg-slate-50">
                                    <td className="px-5 py-3 text-slate-500" colSpan={5}>Opening balance</td>
                                    <td className="px-5 py-3 text-right font-medium text-slate-700">{ledger.opening_display}</td>
                                </tr>
                                {ledger.rows.length === 0 ? (
                                    <tr><td colSpan={6} className="px-5 py-8 text-center text-sm text-slate-500">No entries in this period.</td></tr>
                                ) : ledger.rows.map((row, index) => (
                                    <tr key={index}>
                                        <td className="px-5 py-3 text-slate-600 whitespace-nowrap">{row.date}</td>
                                        <td className="px-5 py-3 text-slate-800">{row.description}</td>
                                        <td className="px-5 py-3 text-slate-500">{row.type}</td>
                                        <td className="px-5 py-3 text-right text-slate-700">{row.debit_display}</td>
                                        <td className="px-5 py-3 text-right text-slate-700">{row.credit_display}</td>
                                        <td className="px-5 py-3 text-right font-medium text-slate-800">{row.balance_display}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-slate-100">
                                    <td className="px-5 py-3 font-semibold text-slate-700" colSpan={3}>Totals</td>
                                    <td className="px-5 py-3 text-right font-medium text-slate-800">{ledger.total_debit_display}</td>
                                    <td className="px-5 py-3 text-right font-medium text-slate-800">{ledger.total_credit_display}</td>
                                    <td className="px-5 py-3 text-right font-bold text-slate-900">{ledger.closing_display}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            )}
        </PortalLayout>
    );
}
