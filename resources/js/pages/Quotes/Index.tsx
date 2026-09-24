import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface QuoteRow {
    id: number;
    number: string;
    customer_name: string | null;
    issue_date: string;
    valid_until: string | null;
    status: string;
    status_label: string;
    status_class: string;
    total_display: string;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { status?: string; customer_id?: string | number };
}

interface Props {
    quotes: TablePagination & { data: QuoteRow[] };
    filters: Filters;
    customers: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    can: { create: boolean };
}

export default function QuotesIndex({ quotes, filters, customers, statuses, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(String(filters.filters.status ?? ''));
    const [customerId, setCustomerId] = useState(String(filters.filters.customer_id ?? ''));

    const params = { search, status, customer_id: customerId, per_page: filters.per_page };

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/quotes', { ...params, sort: filters.sort, direction: filters.direction }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<QuoteRow>[] = [
        {
            key: 'number',
            label: 'Quote',
            sortable: true,
            render: (row) => (
                <Link href={`/portal/quotes/${row.id}`} className="font-medium text-slate-800 hover:text-indigo-600">
                    {row.number}
                </Link>
            ),
        },
        { key: 'customer_name', label: 'Customer' },
        { key: 'issue_date', label: 'Issued', sortable: true },
        { key: 'valid_until', label: 'Valid until', render: (row) => <span className="text-slate-600">{row.valid_until ?? '—'}</span> },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${row.status_class}`}>
                    {row.status_label}
                </span>
            ),
        },
        {
            key: 'total',
            label: 'Total',
            sortable: true,
            className: 'text-right',
            render: (row) => <span className="font-medium text-slate-800">{row.total_display}</span>,
        },
    ];

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Quotes</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Price up work and convert accepted quotes into invoices.</p>
                </div>
                {can.create && (
                    <Link href="/portal/quotes/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        New quote
                    </Link>
                )}
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[200px]">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Quote number or customer" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div className="w-44">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Customer</label>
                    <select value={customerId} onChange={(e) => { setCustomerId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <div className="w-40">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                    <select value={status} onChange={(e) => { setStatus(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                </div>
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Search</button>
            </form>

            <DataTable
                columns={columns}
                rows={quotes.data}
                rowKey={(row) => row.id}
                pagination={quotes}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/quotes"
                params={params}
                emptyTitle="No quotes yet"
                emptyMessage="Create a quote to send pricing to a customer."
            />
        </PortalLayout>
    );
}
