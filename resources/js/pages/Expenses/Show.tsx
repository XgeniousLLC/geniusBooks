import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    expense: {
        id: number;
        date: string;
        description: string;
        reference: string | null;
        notes: string | null;
        category: string | null;
        vendor: string | null;
        account: string | null;
        amount_display: string;
        tax_display: string;
        is_recurring: boolean;
        recurrence_interval: string | null;
        next_recurrence_on: string | null;
        is_voided: boolean;
        void_reason: string | null;
        has_attachment: boolean;
        attachment_name: string | null;
    };
    can: { update: boolean; void: boolean };
}

export default function ExpenseShow({ expense, can }: Props) {
    const [showVoid, setShowVoid] = useState(false);
    const [reason, setReason] = useState('');

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">{expense.description}</h1>
                    <p className="text-sm text-slate-500 mt-0.5">{expense.date}{expense.category ? ` · ${expense.category}` : ''}{expense.vendor ? ` · ${expense.vendor}` : ''}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {expense.has_attachment && (
                        <a href={`/portal/expenses/${expense.id}/attachment`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Download receipt
                        </a>
                    )}
                    {can.update && (
                        <Link href={`/portal/expenses/${expense.id}/edit`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Edit
                        </Link>
                    )}
                    {can.void && (
                        <button onClick={() => setShowVoid((v) => !v)} className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Void
                        </button>
                    )}
                    <Link href="/portal/expenses" className="rounded-xl px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Back</Link>
                </div>
            </div>

            {expense.is_voided && (
                <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    This expense was voided{expense.void_reason ? `: ${expense.void_reason}` : '.'}
                </div>
            )}

            {showVoid && (
                <div className="mb-6 bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-wrap items-end gap-3">
                    <div className="flex-1 min-w-[220px]">
                        <label className="block text-sm font-medium text-slate-700 mb-1.5">Void reason</label>
                        <input value={reason} onChange={(e) => setReason(e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm" />
                    </div>
                    <button
                        onClick={() => router.post(`/portal/expenses/${expense.id}/void`, { reason }, { onSuccess: () => setShowVoid(false) })}
                        disabled={!reason}
                        className="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        Confirm void
                    </button>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Details</h2>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-5 text-sm">
                        <div><dt className="text-xs font-medium uppercase tracking-wide text-slate-400">Account</dt><dd className="mt-1 text-slate-800">{expense.account ?? '—'}</dd></div>
                        <div><dt className="text-xs font-medium uppercase tracking-wide text-slate-400">Reference</dt><dd className="mt-1 text-slate-800">{expense.reference ?? '—'}</dd></div>
                        <div><dt className="text-xs font-medium uppercase tracking-wide text-slate-400">Tax included</dt><dd className="mt-1 text-slate-800">{expense.tax_display}</dd></div>
                        {expense.is_recurring && (
                            <div><dt className="text-xs font-medium uppercase tracking-wide text-slate-400">Recurring</dt><dd className="mt-1 text-slate-800 capitalize">{expense.recurrence_interval} · next {expense.next_recurrence_on}</dd></div>
                        )}
                    </dl>
                    {expense.notes && <p className="mt-5 text-sm text-slate-500 whitespace-pre-line">{expense.notes}</p>}
                    {expense.has_attachment && <p className="mt-4 text-sm text-slate-500">Attachment: {expense.attachment_name}</p>}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Amount</p>
                    <p className="mt-1 text-2xl font-bold text-slate-900">{expense.amount_display}</p>
                </div>
            </div>
        </PortalLayout>
    );
}
