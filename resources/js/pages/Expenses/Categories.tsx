import PortalLayout from '@/layouts/PortalLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Category {
    id: number;
    name: string;
    is_default: boolean;
    expenses_count: number;
}

interface Props {
    categories: Category[];
    can: { create: boolean };
}

export default function ExpenseCategories({ categories, can }: Props) {
    const form = useForm({ name: '' });

    const add = (e: FormEvent) => {
        e.preventDefault();
        form.post('/portal/expenses/categories', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Expense categories</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Default categories plus any custom ones you add.</p>
                </div>
                <Link href="/portal/expenses" className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Back to expenses
                </Link>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                <th className="px-5 py-3 font-medium">Category</th>
                                <th className="px-5 py-3 font-medium">Type</th>
                                <th className="px-5 py-3 font-medium text-right">Expenses</th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {categories.map((category) => (
                                <tr key={category.id}>
                                    <td className="px-5 py-3 text-slate-800">{category.name}</td>
                                    <td className="px-5 py-3 text-slate-500">{category.is_default ? 'Default' : 'Custom'}</td>
                                    <td className="px-5 py-3 text-right text-slate-600">{category.expenses_count}</td>
                                    <td className="px-5 py-3 text-right">
                                        <button
                                            onClick={() => router.delete(`/portal/expenses/categories/${category.id}`, { preserveScroll: true })}
                                            className="text-xs font-medium text-red-600 hover:text-red-800"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {can.create && (
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                        <h2 className="text-sm font-semibold text-slate-800 mb-4">Add a category</h2>
                        <form onSubmit={add} className="space-y-3">
                            <input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Category name"
                                className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm"
                            />
                            {form.errors.name && <p className="text-xs text-red-500">{form.errors.name}</p>}
                            <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                {form.processing ? 'Adding...' : 'Add category'}
                            </button>
                        </form>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
