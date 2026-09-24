import PortalLayout from '@/layouts/PortalLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';

interface Kpi {
    label: string;
    value: string;
    emphasis?: boolean;
}

interface MonthPoint {
    label: string;
    revenue?: number;
    expenses?: number;
    value?: number;
}

interface BreakdownRow {
    label: string;
    value: number;
    display: string;
}

interface TransactionRow {
    id: number;
    occurred_on: string;
    description: string;
    type_label: string;
    direction: 'in' | 'out';
    amount_display: string;
}

interface InvoiceRow {
    id: number;
    number: string;
    customer_name: string | null;
    due_date: string;
    balance_display: string;
    status_label: string;
    status_class: string;
}

interface Props {
    period: { from: string; to: string };
    kpis: Kpi[];
    revenueVsExpenses: MonthPoint[];
    revenueTrend: MonthPoint[];
    expenseBreakdown: BreakdownRow[];
    recentTransactions: TransactionRow[];
    outstandingInvoices: InvoiceRow[];
    selected: { period: string; trend: 'monthly' | 'weekly' };
    companyName: string;
    currency: string;
}

const PERIODS = [
    { value: 'month', label: 'This month' },
    { value: 'quarter', label: 'This quarter' },
    { value: 'year', label: 'This year' },
    { value: 'fy', label: 'Financial year' },
];

export default function Dashboard(props: Props) {
    const { auth } = usePage<PageProps>().props;
    const name = auth?.user?.name ?? 'there';

    const maxMonth = Math.max(1, ...props.revenueVsExpenses.flatMap((p) => [p.revenue ?? 0, p.expenses ?? 0]));
    const maxTrend = Math.max(1, ...props.revenueTrend.map((p) => p.value ?? 0));
    const maxExpense = Math.max(1, ...props.expenseBreakdown.map((r) => r.value));

    const setPeriod = (period: string) => router.get('/portal', { period, trend: props.selected.trend }, { preserveState: true, preserveScroll: true });
    const setTrend = (trend: string) => router.get('/portal', { period: props.selected.period, trend }, { preserveState: true, preserveScroll: true });

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Welcome, {name}</h1>
                    <p className="text-sm text-slate-500 mt-0.5">{props.companyName} · {props.period.from} – {props.period.to}</p>
                </div>
                <div className="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1">
                    {PERIODS.map((period) => (
                        <button
                            key={period.value}
                            onClick={() => setPeriod(period.value)}
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium ${props.selected.period === period.value ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                        >
                            {period.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                {props.kpis.map((kpi) => (
                    <div key={kpi.label} className={`rounded-xl border shadow-sm p-4 ${kpi.emphasis ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-white'}`}>
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{kpi.label}</p>
                        <p className="mt-1 text-xl font-bold text-slate-900">{kpi.value}</p>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold text-slate-800">Revenue vs expenses</h2>
                        <div className="flex gap-3 text-xs">
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-indigo-500" /> Revenue</span>
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-rose-400" /> Expenses</span>
                        </div>
                    </div>
                    <div className="flex items-end justify-between gap-3 h-40">
                        {props.revenueVsExpenses.map((point) => (
                            <div key={point.label} className="flex-1 flex flex-col items-center justify-end gap-1 h-full">
                                <div className="flex items-end gap-1 h-full">
                                    <div className="w-3 rounded-t bg-indigo-500" style={{ height: `${((point.revenue ?? 0) / maxMonth) * 100}%` }} title={String(point.revenue ?? 0)} />
                                    <div className="w-3 rounded-t bg-rose-400" style={{ height: `${((point.expenses ?? 0) / maxMonth) * 100}%` }} title={String(point.expenses ?? 0)} />
                                </div>
                                <span className="text-[10px] text-slate-400">{point.label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold text-slate-800">Revenue trend</h2>
                        <div className="flex gap-1 rounded-lg border border-slate-200 p-0.5">
                            <button onClick={() => setTrend('monthly')} className={`rounded px-2 py-0.5 text-xs ${props.selected.trend === 'monthly' ? 'bg-slate-800 text-white' : 'text-slate-600'}`}>Monthly</button>
                            <button onClick={() => setTrend('weekly')} className={`rounded px-2 py-0.5 text-xs ${props.selected.trend === 'weekly' ? 'bg-slate-800 text-white' : 'text-slate-600'}`}>Weekly</button>
                        </div>
                    </div>
                    <div className="flex items-end gap-1 h-40">
                        {props.revenueTrend.map((point, index) => (
                            <div key={`${point.label}-${index}`} className="flex-1 flex items-end h-full" title={`${point.label}: ${point.value ?? 0}`}>
                                <div className="w-full rounded-t bg-emerald-400" style={{ height: `${((point.value ?? 0) / maxTrend) * 100}%` }} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Expense breakdown</h2>
                    {props.expenseBreakdown.length === 0 ? (
                        <p className="text-sm text-slate-500">No expenses in this period.</p>
                    ) : (
                        <ul className="space-y-3">
                            {props.expenseBreakdown.map((row) => (
                                <li key={row.label}>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-slate-600">{row.label}</span>
                                        <span className="font-medium text-slate-800">{row.display}</span>
                                    </div>
                                    <div className="mt-1 h-1.5 rounded-full bg-slate-100">
                                        <div className="h-1.5 rounded-full bg-amber-400" style={{ width: `${(row.value / maxExpense) * 100}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-sm font-semibold text-slate-800 mb-4">Recent transactions</h2>
                    {props.recentTransactions.length === 0 ? (
                        <p className="text-sm text-slate-500">No transactions yet.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {props.recentTransactions.map((transaction) => (
                                <li key={transaction.id} className="flex items-center justify-between py-2 text-sm">
                                    <div>
                                        <p className="text-slate-700">{transaction.description}</p>
                                        <p className="text-xs text-slate-400">{transaction.occurred_on} · {transaction.type_label}</p>
                                    </div>
                                    <span className={`font-medium ${transaction.direction === 'in' ? 'text-green-600' : 'text-red-600'}`}>
                                        {transaction.direction === 'in' ? '+' : '-'}{transaction.amount_display}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold text-slate-800">Outstanding invoices</h2>
                        <Link href="/portal/invoices" className="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all</Link>
                    </div>
                    {props.outstandingInvoices.length === 0 ? (
                        <p className="text-sm text-slate-500">Nothing outstanding.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {props.outstandingInvoices.map((invoice) => (
                                <li key={invoice.id} className="flex items-center justify-between py-2 text-sm">
                                    <div>
                                        <Link href={`/portal/invoices/${invoice.id}`} className="font-medium text-slate-700 hover:text-indigo-600">{invoice.number}</Link>
                                        <p className="text-xs text-slate-400">{invoice.customer_name} · due {invoice.due_date}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium text-slate-800">{invoice.balance_display}</p>
                                        <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium ${invoice.status_class}`}>{invoice.status_label}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </PortalLayout>
    );
}
