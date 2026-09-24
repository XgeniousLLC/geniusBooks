import PortalLayout from '@/layouts/PortalLayout';
import { Link } from '@inertiajs/react';

interface TransactionRow {
    id: number;
    occurred_on: string;
    description: string;
    type: string;
    direction: 'in' | 'out';
    amount_display: string;
    balance_display: string;
}

interface Props {
    account: {
        id: number;
        name: string;
        type: string;
        currency: string;
        is_active: boolean;
        opening_balance_display: string;
        balance_display: string;
    };
    transactions: TransactionRow[];
    can: { update: boolean };
}

export default function AccountShow({ account, transactions, can }: Props) {
    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-xl font-bold text-slate-900">{account.name}</h1>
                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${account.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600'}`}>
                            {account.is_active ? 'Active' : 'Archived'}
                        </span>
                    </div>
                    <p className="text-sm text-slate-500 mt-0.5 capitalize">{account.type} · {account.currency}</p>
                </div>
                <div className="flex gap-2">
                    {can.update && (
                        <Link href={`/portal/accounts/${account.id}/edit`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Edit
                        </Link>
                    )}
                    <Link href="/portal/accounts" className="rounded-xl px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Back</Link>
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Current balance</p>
                    <p className="mt-1 text-2xl font-bold text-slate-900">{account.balance_display}</p>
                </div>
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Opening balance</p>
                    <p className="mt-1 text-2xl font-bold text-slate-500">{account.opening_balance_display}</p>
                </div>
            </div>

            <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="px-5 py-4 border-b border-slate-100">
                    <h2 className="text-sm font-semibold text-slate-800">Transaction history</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                <th className="px-5 py-3 font-medium">Date</th>
                                <th className="px-5 py-3 font-medium">Description</th>
                                <th className="px-5 py-3 font-medium">Type</th>
                                <th className="px-5 py-3 font-medium text-right">Amount</th>
                                <th className="px-5 py-3 font-medium text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {transactions.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-5 py-12 text-center text-sm text-slate-500">No transactions yet.</td>
                                </tr>
                            ) : (
                                transactions.map((txn) => (
                                    <tr key={txn.id}>
                                        <td className="px-5 py-3 text-slate-600">{txn.occurred_on}</td>
                                        <td className="px-5 py-3 text-slate-800">{txn.description}</td>
                                        <td className="px-5 py-3 text-slate-600">{txn.type}</td>
                                        <td className={`px-5 py-3 text-right font-medium ${txn.direction === 'in' ? 'text-green-600' : 'text-red-600'}`}>
                                            {txn.direction === 'in' ? '+' : '-'}{txn.amount_display}
                                        </td>
                                        <td className="px-5 py-3 text-right text-slate-700">{txn.balance_display}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </PortalLayout>
    );
}
