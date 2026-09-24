import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface CustomerRow {
    id: number;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    is_active: boolean;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { status?: string };
}

interface Props {
    customers: TablePagination & { data: CustomerRow[] };
    filters: Filters;
    can: { create: boolean };
}

export default function CustomersIndex({ customers, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.filters.status ?? '');

    const params = { search, status, per_page: filters.per_page };

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/customers', { ...params, sort: filters.sort, direction: filters.direction }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<CustomerRow>[] = [
        {
            key: 'name',
            label: 'Customer',
            sortable: true,
            render: (row) => (
                <Link href={`/portal/customers/${row.id}`} className="font-medium text-slate-800 hover:text-indigo-600">
                    {row.name}
                </Link>
            ),
        },
        { key: 'company_name', label: 'Company', sortable: true },
        { key: 'email', label: 'Email' },
        { key: 'phone', label: 'Phone' },
        {
            key: 'is_active',
            label: 'Status',
            render: (row) => (
                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${row.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600'}`}>
                    {row.is_active ? 'Active' : 'Archived'}
                </span>
            ),
        },
        {
            key: 'actions',
            label: 'Actions',
            className: 'text-right',
            render: (row) => (
                <Link href={`/portal/customers/${row.id}/edit`} className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    Edit
                </Link>
            ),
        },
    ];

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Customers</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Manage your customer directory.</p>
                </div>
                {can.create && (
                    <div className="flex gap-2">
                        <Link href="/portal/customers/import" className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Import CSV
                        </Link>
                        <Link href="/portal/customers/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            New customer
                        </Link>
                    </div>
                )}
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[220px]">
                    <label htmlFor="search" className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input
                        id="search"
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Name, email or company"
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div className="w-40">
                    <label htmlFor="status" className="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                    <select
                        id="status"
                        value={status}
                        onChange={(e) => { setStatus(e.target.value); applyFilters(); }}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Archived</option>
                    </select>
                </div>
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                    Search
                </button>
            </form>

            <DataTable
                columns={columns}
                rows={customers.data}
                rowKey={(row) => row.id}
                pagination={customers}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/customers"
                params={params}
                emptyTitle="No customers yet"
                emptyMessage="Add your first customer to start invoicing."
                selectable
                bulkActions={[{ value: 'archive', label: 'Archive' }, { value: 'activate', label: 'Activate' }]}
                bulkUrl="/portal/customers/bulk"
            />
        </PortalLayout>
    );
}
