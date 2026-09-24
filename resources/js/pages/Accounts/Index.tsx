import PortalLayout from '@/layouts/PortalLayout';
import { Link } from '@inertiajs/react';

interface Account {
    id: number;
    name: string;
    type: string;
    currency: string;
    is_active: boolean;
    balance_display: string;
}

interface Props {
    accounts: Account[];
    total_display: string;
    can: { create: boolean };
}

export default function AccountsIndex({ accounts, total_display, can }: Props) {
    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Accounts</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Bank and cash accounts with live balances.</p>
                </div>
                {can.create && (
                    <Link href="/portal/accounts/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        New account
                    </Link>
                )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:col-span-1">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Total balance</p>
                    <p className="mt-1 text-2xl font-bold text-slate-900">{total_display}</p>
                </div>
            </div>

            <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                            <th className="px-5 py-3 font-medium">Account</th>
                            <th className="px-5 py-3 font-medium">Type</th>
                            <th className="px-5 py-3 font-medium">Status</th>
                            <th className="px-5 py-3 font-medium text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {accounts.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="px-5 py-12 text-center text-sm text-slate-500">
                                    No accounts yet. Add one to track money in and out.
                                </td>
                            </tr>
                        ) : (
                            accounts.map((account) => (
                                <tr key={account.id} className="hover:bg-slate-50/60">
                                    <td className="px-5 py-3">
                                        <Link href={`/portal/accounts/${account.id}`} className="font-medium text-slate-800 hover:text-indigo-600">
                                            {account.name}
                                        </Link>
                                    </td>
                                    <td className="px-5 py-3 capitalize text-slate-600">{account.type}</td>
                                    <td className="px-5 py-3">
                                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${account.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600'}`}>
                                            {account.is_active ? 'Active' : 'Archived'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right font-medium text-slate-800">{account.balance_display}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </PortalLayout>
    );
}
