import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    account: {
        id: number;
        name: string;
        type: string;
        opening_balance: string;
        is_active: boolean;
    } | null;
    currency: string;
    types: string[];
}

export default function AccountForm({ account, currency, types }: Props) {
    const editing = Boolean(account);

    const form = useForm({
        name: account?.name ?? '',
        type: account?.type ?? 'bank',
        opening_balance: account?.opening_balance ?? '0.00',
        is_active: account?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing && account) {
            form.patch(`/portal/accounts/${account.id}`);
        } else {
            form.post('/portal/accounts');
        }
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{editing ? 'Edit account' : 'New account'}</h1>
                <p className="text-sm text-slate-500 mt-0.5">Balances are derived from opening balance plus transactions.</p>
            </div>

            <form onSubmit={submit} className="max-w-xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <Field label="Account name" required error={form.errors.name}>
                        <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                    </Field>
                    <Field label="Type" required error={form.errors.type}>
                        <select className={inputClass} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            {types.map((type) => (
                                <option key={type} value={type}>{type.charAt(0).toUpperCase() + type.slice(1)}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label={`Opening balance (${currency})`} error={form.errors.opening_balance}>
                        <input type="number" step="0.01" className={inputClass} value={form.data.opening_balance} onChange={(e) => form.setData('opening_balance', e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        Active
                    </label>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create account'}
                    </button>
                    <Link href="/portal/accounts" className="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</Link>
                </div>
            </form>
        </PortalLayout>
    );
}
