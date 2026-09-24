import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    payment: {
        id: number;
        date: string;
        amount_display: string;
        unapplied_display: string;
        method_label: string;
        reference: string | null;
        notes: string | null;
        is_voided: boolean;
        void_reason: string | null;
        customer: { id: number; name: string; email: string | null };
        account: string | null;
        allocations: { invoice_id: number; number: string | null; amount_display: string }[];
    };
    can: { void: boolean; receipt: boolean };
}

export default function PaymentShow({ payment, can }: Props) {
    const [showVoid, setShowVoid] = useState(false);
    const [reason, setReason] = useState('');

    const voidPayment = () => {
        router.post(`/portal/payments/${payment.id}/void`, { reason }, { onSuccess: () => setShowVoid(false) });
    };

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Payment {payment.reference ?? `#${payment.id}`}</h1>
                    <p className="text-sm text-slate-500 mt-0.5">{payment.date} · {payment.method_label} · {payment.account}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {can.receipt && (
                        <button onClick={() => router.post(`/portal/payments/${payment.id}/email`)} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Email receipt
                        </button>
                    )}
                    {can.void && (
                        <button onClick={() => setShowVoid((v) => !v)} className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Void
                        </button>
                    )}
                    <Link href="/portal/payments" className="rounded-xl px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Back</Link>
                </div>
            </div>

            {payment.is_voided && (
                <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    This payment was voided{payment.void_reason ? `: ${payment.void_reason}` : '.'}
                </div>
            )}

            {showVoid && (
                <div className="mb-6 bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-wrap items-end gap-3">
                    <div className="flex-1 min-w-[220px]">
                        <label className="block text-sm font-medium text-slate-700 mb-1.5">Void reason</label>
                        <input value={reason} onChange={(e) => setReason(e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm" />
                    </div>
                    <button onClick={voidPayment} disabled={!reason} className="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                        Confirm void
                    </button>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Allocations</h2>
                    {payment.allocations.length === 0 ? (
                        <p className="text-sm text-slate-500">Not allocated to any invoice — held as customer credit ({payment.unapplied_display}).</p>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                    <th className="py-2 font-medium">Invoice</th>
                                    <th className="py-2 font-medium text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {payment.allocations.map((allocation) => (
                                    <tr key={allocation.invoice_id}>
                                        <td className="py-3">
                                            <Link href={`/portal/invoices/${allocation.invoice_id}`} className="text-indigo-600 hover:text-indigo-800">
                                                {allocation.number}
                                            </Link>
                                        </td>
                                        <td className="py-3 text-right font-medium text-slate-800">{allocation.amount_display}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    {payment.notes && <p className="mt-4 text-sm text-slate-500 whitespace-pre-line">{payment.notes}</p>}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Summary</h2>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between"><dt className="text-slate-500">Customer</dt><dd className="font-medium text-slate-800">{payment.customer.name}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Amount</dt><dd className="font-bold text-slate-900">{payment.amount_display}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Unapplied credit</dt><dd className="font-medium text-slate-800">{payment.unapplied_display}</dd></div>
                    </dl>
                </div>
            </div>
        </PortalLayout>
    );
}
