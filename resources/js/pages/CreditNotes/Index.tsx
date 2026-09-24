import DataTable, { type Column, type TablePagination } from '@/components/DataTable';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface CreditNoteRow {
    id: number;
    number: string;
    customer_name: string | null;
    invoice_number: string | null;
    issue_date: string;
    amount_display: string;
    status: string;
}

interface Filters {
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: { customer_id?: string };
}

interface Props {
    creditNotes: TablePagination & { data: CreditNoteRow[] };
    filters: Filters;
    customers: { id: number; name: string }[];
}

export default function CreditNotesIndex({ creditNotes, filters, customers }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [customerId, setCustomerId] = useState(String(filters.filters.customer_id ?? ''));

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get('/portal/credit-notes', { search, customer_id: customerId, sort: filters.sort, direction: filters.direction, per_page: filters.per_page }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<CreditNoteRow>[] = [
        { key: 'number', label: 'Credit note', render: (row) => <span className="font-medium text-slate-800">{row.number}</span> },
        { key: 'customer_name', label: 'Customer' },
        {
            key: 'invoice_number',
            label: 'Invoice',
            render: (row) => (row.invoice_number ? <span className="text-slate-600">{row.invoice_number}</span> : <span className="text-slate-400">—</span>),
        },
        { key: 'issue_date', label: 'Date', sortable: true },
        { key: 'amount_display', label: 'Amount', sortable: true, className: 'text-right' },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${row.status === 'refunded' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'}`}>
                    {row.status}
                </span>
            ),
        },
    ];

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Credit notes</h1>
                <p className="text-sm text-slate-500 mt-0.5">Credits issued against invoices.</p>
            </div>

            <form onSubmit={applyFilters} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[180px]">
                    <label htmlFor="search" className="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
                    <input id="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Number or customer" className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div className="w-44">
                    <label htmlFor="customer" className="block text-sm font-medium text-slate-700 mb-1.5">Customer</label>
                    <select id="customer" value={customerId} onChange={(e) => { setCustomerId(e.target.value); applyFilters(); }} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Search</button>
            </form>

            <DataTable
                columns={columns}
                rows={creditNotes.data}
                rowKey={(row) => row.id}
                pagination={creditNotes}
                sort={filters.sort}
                direction={filters.direction}
                baseUrl="/portal/credit-notes"
                params={{ search, customer_id: customerId, per_page: filters.per_page }}
                emptyTitle="No credit notes"
                emptyMessage="Issue a credit note from an invoice."
            />

            <p className="mt-4 text-xs text-slate-400">
                Tip: open an invoice to issue a credit note. <Link href="/portal/invoices" className="text-indigo-600 hover:underline">Go to invoices</Link>
            </p>
        </PortalLayout>
    );
}
