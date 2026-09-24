import PortalLayout from '@/layouts/PortalLayout';
import { Link } from '@inertiajs/react';

interface ChartAccount {
    id: number;
    code: string | null;
    name: string;
    is_active: boolean;
    movement_display: string;
    children?: ChartAccount[];
}

interface Group {
    type: string;
    label: string;
    accounts: ChartAccount[];
}

interface Props {
    groups: Group[];
    can: { create: boolean };
}

export default function ChartOfAccounts({ groups, can }: Props) {
    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Chart of accounts</h1>
                    <p className="text-sm text-slate-500 mt-0.5">The account structure behind your reports.</p>
                </div>
                {can.create && (
                    <Link href="/portal/chart-of-accounts/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        New account
                    </Link>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {groups.map((group) => (
                    <div key={group.type} className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h2 className="text-sm font-semibold text-slate-800">{group.label}</h2>
                        </div>
                        {group.accounts.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-slate-500">No accounts.</p>
                        ) : (
                            <table className="min-w-full text-sm">
                                <tbody className="divide-y divide-slate-100">
                                    {group.accounts.map((account) => (
                                        <tr key={account.id}>
                                            <td className="px-5 py-3">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <Link href={`/portal/chart-of-accounts/${account.id}/edit`} className="font-medium text-slate-800 hover:text-indigo-600">
                                                            {account.code && <span className="text-slate-400 mr-2">{account.code}</span>}
                                                            {account.name}
                                                        </Link>
                                                        {account.children && account.children.length > 0 && (
                                                            <ul className="mt-1 ml-4 space-y-1">
                                                                {account.children.map((child) => (
                                                                    <li key={child.id} className="text-slate-500">
                                                                        <Link href={`/portal/chart-of-accounts/${child.id}/edit`} className="hover:text-indigo-600">
                                                                            {child.code && <span className="text-slate-400 mr-2">{child.code}</span>}
                                                                            {child.name}
                                                                        </Link>
                                                                        <span className="float-right text-slate-400">{child.movement_display}</span>
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        )}
                                                    </div>
                                                    <span className="ml-4 shrink-0 font-medium text-slate-700">{account.movement_display}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                ))}
            </div>
        </PortalLayout>
    );
}
