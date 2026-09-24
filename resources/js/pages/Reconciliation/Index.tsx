import PortalLayout from '@/layouts/PortalLayout';
import { router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { type PageProps } from '@/types';

interface Line {
    id: number;
    date: string;
    description: string;
    reference: string | null;
    amount_display: string;
    direction: 'in' | 'out';
    is_matched: boolean;
    is_reconciled: boolean;
    matched_transaction: { id: number; description: string; occurred_on: string } | null;
}

interface Props {
    account: { id: number; name: string; currency: string; balance_display: string };
    lines: Line[];
    candidates: { id: number; label: string }[];
    ledgerAccounts: { id: number; code: string | null; name: string }[];
    summary: { total: number; matched: number; reconciled: number; unmatched: number };
    can: { manage: boolean };
    currency: string;
}

export default function ReconciliationIndex({ account, lines, candidates, ledgerAccounts, summary }: Props) {
    const { flash } = usePage<PageProps>().props;
    const importForm = useForm<{ file: File | null }>({ file: null });
    const [ledgerAccountId, setLedgerAccountId] = useState('');
    const [selection, setSelection] = useState<Record<number, string>>({});

    const upload = (e: FormEvent) => {
        e.preventDefault();
        importForm.post(`/portal/accounts/${account.id}/reconcile/import`, { forceFormData: true, onSuccess: () => importForm.reset() });
    };

    const post = (url: string, data: Record<string, unknown> = {}) => router.post(url, data, { preserveScroll: true });

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Reconcile — {account.name}</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Balance {account.balance_display} · {summary.matched}/{summary.total} matched · {summary.reconciled} reconciled</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <a href={`/portal/accounts/${account.id}`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to account</a>
                    <button onClick={() => post(`/portal/accounts/${account.id}/reconcile/auto-match`)} className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Auto-match
                    </button>
                </div>
            </div>

            {flash?.importErrors && flash.importErrors.length > 0 && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p className="font-medium">Import failed — no lines were saved:</p>
                    <ul className="mt-1 list-disc list-inside">
                        {flash.importErrors.slice(0, 20).map((error, index) => <li key={index}>Row {error.row}: {error.message}</li>)}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-3">Import statement (CSV)</h2>
                    <form onSubmit={upload} className="flex flex-wrap items-end gap-3">
                        <div className="flex-1 min-w-[220px]">
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            {importForm.errors.file && <p className="mt-1 text-xs text-red-500">{importForm.errors.file}</p>}
                        </div>
                        <button type="submit" disabled={importForm.processing || !importForm.data.file} className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50">
                            {importForm.processing ? 'Importing...' : 'Upload'}
                        </button>
                    </form>
                    <p className="mt-2 text-xs text-slate-400">Columns: date, description, amount (negative = money out), reference (optional).</p>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-3">New transactions post to</h2>
                    <select value={ledgerAccountId} onChange={(e) => setLedgerAccountId(e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <option value="">Default (no ledger account)</option>
                        {ledgerAccounts.map((a) => <option key={a.id} value={a.id}>{a.code ? `${a.code} · ` : ''}{a.name}</option>)}
                    </select>
                    <p className="mt-2 text-xs text-slate-400">Used when you create a transaction from an unmatched line.</p>
                </div>
            </div>

            <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                <th className="px-5 py-3 font-medium">Date</th>
                                <th className="px-5 py-3 font-medium">Description</th>
                                <th className="px-5 py-3 font-medium text-right">Amount</th>
                                <th className="px-5 py-3 font-medium">Status</th>
                                <th className="px-5 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lines.length === 0 ? (
                                <tr><td colSpan={5} className="px-5 py-12 text-center text-sm text-slate-500">No statement lines yet. Import a CSV to begin.</td></tr>
                            ) : lines.map((line) => (
                                <tr key={line.id} className={line.is_reconciled ? 'bg-green-50/40' : ''}>
                                    <td className="px-5 py-3 text-slate-600 whitespace-nowrap">{line.date}</td>
                                    <td className="px-5 py-3 text-slate-800">
                                        {line.description}
                                        {line.reference && <span className="block text-xs text-slate-400">{line.reference}</span>}
                                        {line.matched_transaction && <span className="block text-xs text-indigo-600">Matched: {line.matched_transaction.description}</span>}
                                    </td>
                                    <td className={`px-5 py-3 text-right font-medium whitespace-nowrap ${line.direction === 'in' ? 'text-green-600' : 'text-red-600'}`}>
                                        {line.direction === 'in' ? '+' : '-'}{line.amount_display}
                                    </td>
                                    <td className="px-5 py-3">
                                        {line.is_reconciled ? (
                                            <span className="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Reconciled</span>
                                        ) : line.is_matched ? (
                                            <span className="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">Matched</span>
                                        ) : (
                                            <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Unmatched</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 whitespace-nowrap">
                                        {line.is_matched ? (
                                            <div className="flex items-center gap-3">
                                                <button onClick={() => post(`/portal/accounts/${account.id}/reconcile/${line.id}/reconcile`, { reconciled: !line.is_reconciled })} className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                                    {line.is_reconciled ? 'Undo reconcile' : 'Reconcile'}
                                                </button>
                                                <button onClick={() => post(`/portal/accounts/${account.id}/reconcile/${line.id}/unmatch`)} className="text-xs font-medium text-slate-500 hover:text-slate-700">Unmatch</button>
                                            </div>
                                        ) : (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <select
                                                    value={selection[line.id] ?? ''}
                                                    onChange={(e) => setSelection((prev) => ({ ...prev, [line.id]: e.target.value }))}
                                                    className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs max-w-[220px]"
                                                >
                                                    <option value="">Match a transaction…</option>
                                                    {candidates.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                                                </select>
                                                <button
                                                    disabled={!selection[line.id]}
                                                    onClick={() => post(`/portal/accounts/${account.id}/reconcile/${line.id}/match`, { transaction_id: selection[line.id] })}
                                                    className="text-xs font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-40"
                                                >
                                                    Match
                                                </button>
                                                <button
                                                    onClick={() => post(`/portal/accounts/${account.id}/reconcile/${line.id}/create-transaction`, ledgerAccountId ? { ledger_account_id: ledgerAccountId } : {})}
                                                    className="text-xs font-medium text-green-600 hover:text-green-800"
                                                >
                                                    Create transaction
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </PortalLayout>
    );
}
