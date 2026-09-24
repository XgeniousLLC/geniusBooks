import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface ResultItem {
    label: string;
    sublabel: string | null;
    url: string;
}

interface Group {
    title: string;
    items: ResultItem[];
}

interface Props {
    query: string;
    groups: Group[];
}

export default function SearchIndex({ query, groups }: Props) {
    const [term, setTerm] = useState(query);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get('/portal/search', { q: term }, { preserveState: true });
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Search</h1>
                <p className="text-sm text-slate-500 mt-0.5">Find customers, invoices, payments, expenses and transactions.</p>
            </div>

            <form onSubmit={submit} className="mb-6 flex gap-2">
                <input
                    autoFocus
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                    placeholder="Search everything…"
                    className="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <button type="submit" className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
            </form>

            {query.length < 2 ? (
                <p className="text-sm text-slate-500">Type at least two characters to search.</p>
            ) : groups.length === 0 ? (
                <p className="text-sm text-slate-500">No results for “{query}”.</p>
            ) : (
                <div className="space-y-6">
                    {groups.map((group) => (
                        <div key={group.title} className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                            <div className="px-5 py-3 border-b border-slate-100">
                                <h2 className="text-sm font-semibold text-slate-800">{group.title}</h2>
                            </div>
                            <ul className="divide-y divide-slate-100">
                                {group.items.map((item, index) => (
                                    <li key={index}>
                                        <Link href={item.url} className="flex items-center justify-between px-5 py-3 hover:bg-slate-50">
                                            <span className="text-sm text-slate-800">{item.label}</span>
                                            {item.sublabel && <span className="text-xs text-slate-400">{item.sublabel}</span>}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            )}
        </PortalLayout>
    );
}
