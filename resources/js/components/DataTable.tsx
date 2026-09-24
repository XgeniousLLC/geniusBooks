import { Link, router } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';

export interface Column<T> {
    key: string;
    label: string;
    sortable?: boolean;
    className?: string;
    render?: (row: T) => ReactNode;
}

export interface TablePagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface BulkAction {
    value: string;
    label: string;
}

interface Props<T> {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    pagination: TablePagination;
    sort: string;
    direction: 'asc' | 'desc';
    baseUrl: string;
    params?: Record<string, unknown>;
    emptyTitle?: string;
    emptyMessage?: string;
    selectable?: boolean;
    bulkActions?: BulkAction[];
    bulkUrl?: string;
}

export default function DataTable<T>({
    columns,
    rows,
    rowKey,
    pagination,
    sort,
    direction,
    baseUrl,
    params = {},
    emptyTitle = 'Nothing here yet',
    emptyMessage = 'No records match your filters.',
    selectable = false,
    bulkActions = [],
    bulkUrl,
}: Props<T>) {
    const [selected, setSelected] = useState<(string | number)[]>([]);
    const [action, setAction] = useState(bulkActions[0]?.value ?? '');

    const allSelected = rows.length > 0 && selected.length === rows.length;
    const toggleAll = () => setSelected(allSelected ? [] : rows.map((row) => rowKey(row)));
    const toggleOne = (key: string | number) => setSelected((prev) => (prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]));

    const toggleSort = (key: string) => {
        const nextDirection = sort === key && direction === 'asc' ? 'desc' : 'asc';
        router.get(baseUrl, { ...params, sort: key, direction: nextDirection }, { preserveState: true, preserveScroll: true });
    };

    const applyBulk = () => {
        if (!bulkUrl || selected.length === 0) {
            return;
        }
        const label = bulkActions.find((a) => a.value === action)?.label ?? action;
        if (!window.confirm(`${label} ${selected.length} item(s)?`)) {
            return;
        }
        router.post(bulkUrl, { ids: selected, action }, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    };

    const columnCount = columns.length + (selectable ? 1 : 0);

    return (
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            {selectable && selected.length > 0 && (
                <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-slate-50 px-5 py-3">
                    <span className="text-sm text-slate-600">{selected.length} selected</span>
                    <select value={action} onChange={(e) => setAction(e.target.value)} className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm">
                        {bulkActions.map((bulkAction) => (
                            <option key={bulkAction.value} value={bulkAction.value}>{bulkAction.label}</option>
                        ))}
                    </select>
                    <button onClick={applyBulk} className="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-900">
                        Apply
                    </button>
                    <button onClick={() => setSelected([])} className="text-sm text-slate-500 hover:text-slate-700">Clear</button>
                </div>
            )}

            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                            {selectable && (
                                <th className="px-5 py-3 w-10">
                                    <input
                                        type="checkbox"
                                        aria-label="Select all"
                                        checked={allSelected}
                                        onChange={toggleAll}
                                        className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </th>
                            )}
                            {columns.map((column) => (
                                <th key={column.key} className={`px-5 py-3 font-medium ${column.className ?? ''}`}>
                                    {column.sortable ? (
                                        <button type="button" onClick={() => toggleSort(column.key)} className="inline-flex items-center gap-1 hover:text-slate-600">
                                            {column.label}
                                            {sort === column.key && <span aria-hidden="true">{direction === 'asc' ? '↑' : '↓'}</span>}
                                        </button>
                                    ) : (
                                        column.label
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={columnCount} className="px-5 py-12 text-center">
                                    <p className="text-sm font-medium text-slate-700">{emptyTitle}</p>
                                    <p className="text-xs text-slate-500 mt-1">{emptyMessage}</p>
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => {
                                const key = rowKey(row);
                                return (
                                    <tr key={key} className="hover:bg-slate-50/60">
                                        {selectable && (
                                            <td className="px-5 py-3">
                                                <input
                                                    type="checkbox"
                                                    aria-label="Select row"
                                                    checked={selected.includes(key)}
                                                    onChange={() => toggleOne(key)}
                                                    className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                            </td>
                                        )}
                                        {columns.map((column) => (
                                            <td key={column.key} className={`px-5 py-3 align-middle ${column.className ?? ''}`}>
                                                {column.render ? column.render(row) : String((row as Record<string, unknown>)[column.key] ?? '')}
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {pagination.last_page > 1 && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
                    <p className="text-xs text-slate-500">
                        Showing page {pagination.current_page} of {pagination.last_page} · {pagination.total} total
                    </p>
                    <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                        {pagination.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveScroll
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`rounded-lg px-3 py-1.5 text-xs font-medium ${
                                        link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                />
                            ) : (
                                <span
                                    key={index}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-300"
                                />
                            )
                        )}
                    </nav>
                </div>
            )}
        </div>
    );
}
