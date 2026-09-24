import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface ExpenseRow {
    id: number;
    date: string;
    description: string;
    category: string | null;
    vendor: string | null;
    amount_display: string;
    has_attachment: boolean;
    is_recurring: boolean;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { expense_category_id?: string; vendor_id?: string; bank_account_id?: string; from?: string; to?: string };
}

interface Props {
    expenses: TablePagination & { data: ExpenseRow[] };
    filters: Filters;
    categories: { id: number; name: string }[];
    vendors: { id: number; name: string }[];
    accounts: { id: number; name: string }[];
    totals: { value: string };
    can: { create: boolean };
}

export default function ExpensesIndex({ expenses, filters, categories, vendors, accounts, totals, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(String(filters.filters.expense_category_id ?? ''));
    const [vendorId, setVendorId] = useState(String(filters.filters.vendor_id ?? ''));
    const [accountId, setAccountId] = useState(String(filters.filters.bank_account_id ?? ''));
    const [from, setFrom] = useState(filters.filters.from ?? '');
    const [to, setTo] = useState(filters.filters.to ?? '');

    const params = { search, expense_category_id: categoryId, vendor_id: vendorId, bank_account_id: accountId, from, to, per_page: filters.per_page };

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/expenses', { ...params, sort: filters.sort, direction: filters.direction }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<ExpenseRow>[] = [
        {
            key: 'date',
            label: 'Date',
            sortable: true,
            render: (row) => (
                <Link href={`/portal/expenses/${row.id}`} className="font-medium text-slate-800 hover:text-indigo-600">
                    {row.date}
                </Link>
            ),
        },
        {
            key: 'description',
            label: 'Description',
            render: (row) => (
                <div className="flex items-center gap-2">
                    <span className="text-slate-800">{row.description}</span>
                    {row.is_recurring && <span className="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium uppercase text-indigo-600">Recurring</span>}
                    {row.has_attachment && <span title="Has attachment" className="text-slate-400">📎</span>}
                </div>
            ),
        },
        { key: 'category', label: 'Category' },
        { key: 'vendor', label: 'Vendor' },
        {
            key: 'amount_display',
            label: 'Amount',
            sortable: true,
            className: 'text-right',
            render: (row) => <span className="font-medium text-slate-800">{row.amount_display}</span>,
        },
    ];

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Expenses</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Money out, by category and vendor.</p>
                </div>
                <div className="flex gap-2">
                    <Link href="/portal/expenses/categories" className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Categories
                    </Link>
                    {can.create && (
                        <Link href="/portal/expenses/create" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            New expense
                        </Link>
                    )}
                </div>
            </div>

            <div className="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Total (filtered)</p>
                    <p className="mt-1 text-2xl font-bold text-slate-900">{totals.value}</p>
                </div>
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[160px]">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Description or reference" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div className="w-40">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Category</label>
                    <select value={categoryId} onChange={(e) => { setCategoryId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <div className="w-40">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Vendor</label>
                    <select value={vendorId} onChange={(e) => { setVendorId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                    </select>
                </div>
                <div className="w-40">
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Account</label>
                    <select value={accountId} onChange={(e) => { setAccountId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
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
                rows={expenses.data}
                rowKey={(row) => row.id}
                pagination={expenses}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/expenses"
                params={params}
                emptyTitle="No expenses yet"
                emptyMessage="Record your first expense to track spending."
            />
        </PortalLayout>
    );
}
