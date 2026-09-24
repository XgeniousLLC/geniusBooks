import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface ProductRow {
    id: number;
    name: string;
    sku: string | null;
    type: 'product' | 'service';
    category: string | null;
    unit_price_display: string;
    tax_rate: string | number | null;
    is_active: boolean;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { type?: string; status?: string; category?: string };
}

interface Props {
    products: TablePagination & { data: ProductRow[] };
    filters: Filters;
    categories: string[];
    can: { create: boolean };
}

export default function ProductsIndex({ products, filters, categories, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.filters.type ?? '');
    const [status, setStatus] = useState(filters.filters.status ?? '');
    const [category, setCategory] = useState(filters.filters.category ?? '');

    const params = { search, type, status, category, per_page: filters.per_page };

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/products', { ...params, sort: filters.sort, direction: filters.direction }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<ProductRow>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => (
                <div>
                    <p className="font-medium text-slate-800">{row.name}</p>
                    {row.sku && <p className="text-xs text-slate-400">{row.sku}</p>}
                </div>
            ),
        },
        {
            key: 'type',
            label: 'Type',
            render: (row) => <span className="capitalize text-slate-600">{row.type}</span>,
        },
        { key: 'category', label: 'Category' },
        {
            key: 'unit_price',
            label: 'Unit price',
            sortable: true,
            className: 'text-right',
            render: (row) => <span className="font-medium text-slate-800">{row.unit_price_display}</span>,
        },
        {
            key: 'tax_rate',
            label: 'Tax',
            className: 'text-right',
            render: (row) => <span className="text-slate-600">{row.tax_rate != null ? `${Number(row.tax_rate)}%` : '—'}</span>,
        },
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
                <Link href={`/portal/products/${row.id}/edit`} className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    Edit
                </Link>
            ),
        },
    ];

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Products &amp; Services</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Reusable items for faster invoicing.</p>
                </div>
                {can.create && (
                    <div className="flex gap-2">
                        <Link href="/portal/products/import" className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Import CSV
                        </Link>
                        <Link href="/portal/products/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            New item
                        </Link>
                    </div>
                )}
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[200px]">
                    <label htmlFor="search" className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input
                        id="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Name or SKU"
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div className="w-36">
                    <label htmlFor="type" className="block text-sm font-medium text-slate-700 mb-1.5">Type</label>
                    <select id="type" value={type} onChange={(e) => { setType(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All</option>
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                </div>
                <div className="w-40">
                    <label htmlFor="category" className="block text-sm font-medium text-slate-700 mb-1.5">Category</label>
                    <select id="category" value={category} onChange={(e) => { setCategory(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All</option>
                        {categories.map((c) => (
                            <option key={c} value={c}>{c}</option>
                        ))}
                    </select>
                </div>
                <div className="w-36">
                    <label htmlFor="status" className="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                    <select id="status" value={status} onChange={(e) => { setStatus(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                rows={products.data}
                rowKey={(row) => row.id}
                pagination={products}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/products"
                params={params}
                emptyTitle="No products or services yet"
                emptyMessage="Add an item to speed up invoicing."
                selectable
                bulkActions={[{ value: 'archive', label: 'Archive' }, { value: 'activate', label: 'Activate' }]}
                bulkUrl="/portal/products/bulk"
            />
        </PortalLayout>
    );
}
