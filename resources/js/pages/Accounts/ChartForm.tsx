import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    account: {
        id: number;
        code: string | null;
        name: string;
        type: string;
        parent_id: number | null;
        is_active: boolean;
    } | null;
    types: { value: string; label: string }[];
    parents: { id: number; label: string }[];
}

export default function ChartAccountForm({ account, types, parents }: Props) {
    const editing = Boolean(account);

    const form = useForm({
        code: account?.code ?? '',
        name: account?.name ?? '',
        type: account?.type ?? 'expense',
        parent_id: (account?.parent_id ?? '') as number | '',
        is_active: account?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing && account) {
            form.put(`/portal/chart-of-accounts/${account.id}`);
        } else {
            form.post('/portal/chart-of-accounts');
        }
    };

    const remove = () => {
        if (account && window.confirm('Delete this account?')) {
            form.delete(`/portal/chart-of-accounts/${account.id}`);
        }
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{editing ? 'Edit account' : 'New account'}</h1>
                <p className="text-sm text-slate-500 mt-0.5">Accounts make up the chart of accounts for reporting.</p>
            </div>

            <form onSubmit={submit} className="max-w-xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Code" error={form.errors.code}>
                            <input className={inputClass} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                        </Field>
                        <Field label="Type" required error={form.errors.type}>
                            <select className={inputClass} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                {types.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                            </select>
                        </Field>
                    </div>
                    <Field label="Name" required error={form.errors.name}>
                        <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                    </Field>
                    <Field label="Parent account" error={form.errors.parent_id}>
                        <select className={inputClass} value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">None (top level)</option>
                            {parents.map((parent) => <option key={parent.id} value={parent.id}>{parent.label}</option>)}
                        </select>
                    </Field>
                    <label className="flex items-center gap-3 text-sm text-slate-700">
                        <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                        Active
                    </label>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create account'}
                    </button>
                    {editing && (
                        <button type="button" onClick={remove} className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Delete
                        </button>
                    )}
                    <Link href="/portal/chart-of-accounts" className="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</Link>
                </div>
            </form>
        </PortalLayout>
    );
}
