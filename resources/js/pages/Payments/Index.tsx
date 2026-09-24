import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface PaymentRow {
    id: number;
    date: string;
    customer_name: string | null;
    method_label: string;
    reference: string | null;
    amount_display: string;
    is_voided: boolean;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { customer_id?: string; bank_account_id?: string; method?: string };
}

interface Props {
    payments: TablePagination & { data: PaymentRow[] };
    filters: Filters;
    customers: { id: number; name: string }[];
    accounts: { id: number; name: string }[];
    methods: { value: string; label: string }[];
    can: { create: boolean };
}

export default function PaymentsIndex({ payments, filters, customers, accounts, methods, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [customerId, setCustomerId] = useState(String(filters.filters.customer_id ?? ''));
    const [accountId, setAccountId] = useState(String(filters.filters.bank_account_id ?? ''));
    const [method, setMethod] = useState(String(filters.filters.method ?? ''));

    const params = { search, customer_id: customerId, bank_account_id: accountId, method, per_page: filters.per_page };

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/payments', { ...params, sort: filters.sort, direction: filters.direction }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<PaymentRow>[] = [
        {
            key: 'date',
            label: 'Date',
            sortable: true,
            render: (row) => (
                <Link href={`/portal/payments/${row.id}`} className="font-medium text-slate-800 hover:text-indigo-600">
                    {row.date}
                </Link>
            ),
        },
        { key: 'customer_name', label: 'Customer' },
        { key: 'method_label', label: 'Method' },
        { key: 'reference', label: 'Reference' },
        {
            key: 'amount_display',
            label: 'Amount',
            sortable: true,
            className: 'text-right',
            render: (row) => (
                <span className={`font-medium ${row.is_voided ? 'text-slate-400 line-through' : 'text-slate-800'}`}>
                    {row.amount_display}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (row.is_voided
                ? <span className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-500">Voided</span>
                : <span className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800">Received</span>),
        },
    ];

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Payments</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Money received and how it was allocated.</p>
                </div>
                {can.create && (
                    <Link href="/portal/payments/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Record payment
                    </Link>
                )}
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[180px]">
                    <label htmlFor="search" className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input id="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Reference or customer" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div className="w-40">
                    <label htmlFor="customer" className="block text-sm font-medium text-slate-700 mb-1.5">Customer</label>
                    <select id="customer" value={customerId} onChange={(e) => { setCustomerId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <div className="w-40">
                    <label htmlFor="account" className="block text-sm font-medium text-slate-700 mb-1.5">Account</label>
                    <select id="account" value={accountId} onChange={(e) => { setAccountId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </div>
                <div className="w-40">
                    <label htmlFor="method" className="block text-sm font-medium text-slate-700 mb-1.5">Method</label>
                    <select id="method" value={method} onChange={(e) => { setMethod(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {methods.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                    </select>
                </div>
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Search</button>
            </form>

            <DataTable
                columns={columns}
                rows={payments.data}
                rowKey={(row) => row.id}
                pagination={payments}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/payments"
                params={params}
                emptyTitle="No payments yet"
                emptyMessage="Record a payment against an invoice."
            />
        </PortalLayout>
    );
}
