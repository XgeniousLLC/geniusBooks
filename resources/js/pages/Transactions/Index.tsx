import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface TransactionRow {
    id: number;
    occurred_on: string;
    description: string;
    type_label: string;
    direction: 'in' | 'out';
    amount_display: string;
    bank_account: string | null;
    ledger_account: string | null;
    source_url: string | null;
    can_reverse: boolean;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { type?: string; direction?: string; bank_account_id?: string; ledger_account_id?: string; from?: string; to?: string };
}

interface Props {
    transactions: TablePagination & { data: TransactionRow[] };
    filters: Filters;
    accounts: { id: number; name: string }[];
    ledgerAccounts: { id: number; code: string | null; name: string }[];
    types: { value: string; label: string }[];
    summary: { income: string; expense: string; net: string };
    can: { create: boolean };
}

export default function TransactionsIndex({ transactions, filters, accounts, ledgerAccounts, types, summary, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(String(filters.filters.type ?? ''));
    const [direction, setDirection] = useState(String(filters.filters.direction ?? ''));
    const [accountId, setAccountId] = useState(String(filters.filters.bank_account_id ?? ''));
    const [ledgerAccountId, setLedgerAccountId] = useState(String(filters.filters.ledger_account_id ?? ''));
    const [from, setFrom] = useState(filters.filters.from ?? '');
    const [to, setTo] = useState(filters.filters.to ?? '');

    const params = { search, type, direction, bank_account_id: accountId, ledger_account_id: ledgerAccountId, from, to, per_page: filters.per_page };

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/transactions', { ...params, sort: filters.sort, direction: filters.direction }, { preserveState: true, preserveScroll: true });
    };

    const reverse = (row: TransactionRow) => {
        const reason = window.prompt('Reason for reversal?');
        if (reason) {
            router.post(`/portal/transactions/${row.id}/reverse`, { reason }, { preserveScroll: true });
        }
    };

    const columns: Column<TransactionRow>[] = [
        { key: 'occurred_on', label: 'Date', sortable: true },
        {
            key: 'description',
            label: 'Description',
            render: (row) => (
                <div>
                    <span className="text-slate-800">{row.description}</span>
                    {row.source_url && (
                        <Link href={row.source_url} className="ml-2 text-xs text-indigo-600 hover:underline">View source</Link>
                    )}
                </div>
            ),
        },
        { key: 'type_label', label: 'Type' },
        { key: 'bank_account', label: 'Account', render: (row) => <span className="text-slate-600">{row.bank_account ?? '—'}</span> },
        {
            key: 'amount_display',
            label: 'Amount',
            sortable: true,
            className: 'text-right',
            render: (row) => (
                <span className={`font-medium ${row.direction === 'in' ? 'text-green-600' : 'text-red-600'}`}>
                    {row.direction === 'in' ? '+' : '-'}{row.amount_display}
                </span>
            ),
        },
        {
            key: 'actions',
            label: '',
            className: 'text-right',
            render: (row) => (row.can_reverse ? (
                <button onClick={() => reverse(row)} className="text-xs font-medium text-red-600 hover:text-red-800">Reverse</button>
            ) : null),
        },
    ];

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Transactions</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Every money movement, from every module.</p>
                </div>
                {can.create && (
                    <div className="flex gap-2">
                        <Link href="/portal/transactions/create?type=transfer" className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Transfer</Link>
                        <Link href="/portal/transactions/create?type=adjustment" className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Adjustment</Link>
                        <Link href="/portal/transactions/create?type=income" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Record income</Link>
                    </div>
                )}
            </div>

            <div className="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                {([['Income', summary.income], ['Expenses', summary.expense], ['Net', summary.net]] as const).map(([label, value]) => (
                    <div key={label} className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
                        <p className="mt-1 text-2xl font-bold text-slate-900">{value}</p>
                    </div>
                ))}
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[160px]">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Description" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div className="w-40">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Type</label>
                    <select value={type} onChange={(e) => { setType(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </select>
                </div>
                <div className="w-32">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Direction</label>
                    <select value={direction} onChange={(e) => { setDirection(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        <option value="in">In</option>
                        <option value="out">Out</option>
                    </select>
                </div>
                <div className="w-40">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Account</label>
                    <select value={accountId} onChange={(e) => { setAccountId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </div>
                <div className="w-44">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Ledger account</label>
                    <select value={ledgerAccountId} onChange={(e) => { setLedgerAccountId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {ledgerAccounts.map((a) => <option key={a.id} value={a.id}>{a.code ? `${a.code} · ` : ''}{a.name}</option>)}
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
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Search</button>
            </form>

            <DataTable
                columns={columns}
                rows={transactions.data}
                rowKey={(row) => row.id}
                pagination={transactions}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/transactions"
                params={params}
                emptyTitle="No transactions yet"
                emptyMessage="Record a payment, expense or income to see entries here."
            />
        </PortalLayout>
    );
}
