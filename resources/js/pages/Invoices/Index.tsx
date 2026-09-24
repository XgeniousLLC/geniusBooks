import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface InvoiceRow {
    id: number;
    number: string;
    customer_name: string | null;
    issue_date: string;
    due_date: string;
    status: string;
    status_label: string;
    status_class: string;
    total_display: string;
    balance_display: string;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { status?: string; customer_id?: string | number };
}

interface Props {
    invoices: TablePagination & { data: InvoiceRow[] };
    filters: Filters;
    customers: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    can: { create: boolean };
}

export default function InvoicesIndex({ invoices, filters, customers, statuses, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(String(filters.filters.status ?? ''));
    const [customerId, setCustomerId] = useState(String(filters.filters.customer_id ?? ''));

    const params = { search, status, customer_id: customerId, per_page: filters.per_page };

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/invoices', { ...params, sort: filters.sort, direction: filters.direction }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<InvoiceRow>[] = [
        {
            key: 'number',
            label: 'Invoice',
            sortable: true,
            render: (row) => (
                <Link href={`/portal/invoices/${row.id}`} className="font-medium text-slate-800 hover:text-indigo-600">
                    {row.number}
                </Link>
            ),
        },
        { key: 'customer_name', label: 'Customer' },
        { key: 'issue_date', label: 'Issued', sortable: true },
        { key: 'due_date', label: 'Due', sortable: true },
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
                    <h1 className="text-xl font-bold text-slate-900">Invoices</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Create, track and manage invoices.</p>
                </div>
                {can.create && (
                    <Link href="/portal/invoices/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        New invoice
                    </Link>
                )}
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[200px]">
                    <label htmlFor="search" className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input
                        id="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Invoice number or customer"
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div className="w-44">
                    <label htmlFor="customer" className="block text-sm font-medium text-slate-700 mb-1.5">Customer</label>
                    <select id="customer" value={customerId} onChange={(e) => { setCustomerId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All</option>
                        {customers.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                </div>
                <div className="w-40">
                    <label htmlFor="status" className="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                    <select id="status" value={status} onChange={(e) => { setStatus(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                    </select>
                </div>
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                    Search
                </button>
            </form>

            <DataTable
                columns={columns}
                rows={invoices.data}
                rowKey={(row) => row.id}
                pagination={invoices}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/invoices"
                params={params}
                emptyTitle="No invoices yet"
                emptyMessage="Create your first invoice to get paid."
            />
        </PortalLayout>
    );
}
