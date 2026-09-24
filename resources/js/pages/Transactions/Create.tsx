import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface Option {
    id: number;
    name: string;
}

interface LedgerOption {
    id: number;
    code: string | null;
    name: string;
}

interface Props {
    type: string;
    accounts: Option[];
    revenueAccounts: LedgerOption[];
    expenseAccounts: LedgerOption[];
    currency: string;
    defaults: { date: string };
}

type EntryType = 'transfer' | 'adjustment' | 'income';

export default function TransactionCreate({ type, accounts, revenueAccounts, expenseAccounts, currency, defaults }: Props) {
    const [entryType, setEntryType] = useState<EntryType>((['transfer', 'adjustment', 'income'].includes(type) ? type : 'transfer') as EntryType);

    const form = useForm({
        from_bank_account_id: (accounts[0]?.id ?? '') as number | '',
        to_bank_account_id: (accounts[1]?.id ?? '') as number | '',
        bank_account_id: (accounts[0]?.id ?? '') as number | '',
        ledger_account_id: '' as number | '',
        direction: 'in',
        amount: '',
        date: defaults.date,
        description: '',
        reason: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (entryType === 'transfer') {
            form.post('/portal/transactions/transfer');
        } else if (entryType === 'adjustment') {
            form.post('/portal/transactions/adjustment');
        } else {
            form.post('/portal/transactions/income');
        }
    };

    const ledgerOptions = entryType === 'income' ? revenueAccounts : expenseAccounts;

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">New transaction</h1>
                <p className="text-sm text-slate-500 mt-0.5">Transfers, adjustments and direct income.</p>
            </div>

            <div className="mb-6 flex gap-2">
                {(['transfer', 'adjustment', 'income'] as EntryType[]).map((option) => (
                    <button
                        key={option}
                        type="button"
                        onClick={() => setEntryType(option)}
                        className={`rounded-xl px-4 py-2 text-sm font-medium capitalize ${entryType === option ? 'bg-indigo-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}
                    >
                        {option}
                    </button>
                ))}
            </div>

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    {entryType === 'transfer' && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <Field label="From account" required error={form.errors.from_bank_account_id}>
                                <select className={inputClass} value={form.data.from_bank_account_id} onChange={(e) => form.setData('from_bank_account_id', Number(e.target.value))}>
                                    {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                </select>
                            </Field>
                            <Field label="To account" required error={form.errors.to_bank_account_id}>
                                <select className={inputClass} value={form.data.to_bank_account_id} onChange={(e) => form.setData('to_bank_account_id', Number(e.target.value))}>
                                    {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                </select>
                            </Field>
                        </div>
                    )}

                    {entryType !== 'transfer' && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <Field label="Account" required error={form.errors.bank_account_id}>
                                <select className={inputClass} value={form.data.bank_account_id} onChange={(e) => form.setData('bank_account_id', Number(e.target.value))}>
                                    {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Ledger account" required error={form.errors.ledger_account_id}>
                                <select className={inputClass} value={form.data.ledger_account_id} onChange={(e) => form.setData('ledger_account_id', e.target.value === '' ? '' : Number(e.target.value))}>
                                    <option value="">Select</option>
                                    {ledgerOptions.map((a) => <option key={a.id} value={a.id}>{a.code ? `${a.code} · ` : ''}{a.name}</option>)}
                                </select>
                            </Field>
                        </div>
                    )}

                    {entryType === 'adjustment' && (
                        <Field label="Direction" required error={form.errors.direction}>
                            <select className={inputClass} value={form.data.direction} onChange={(e) => form.setData('direction', e.target.value)}>
                                <option value="in">Money in</option>
                                <option value="out">Money out</option>
                            </select>
                        </Field>
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label={`Amount (${currency})`} required error={form.errors.amount}>
                            <input type="number" min="0" step="0.01" className={inputClass} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                        </Field>
                        <Field label="Date" required error={form.errors.date}>
                            <input type="date" className={inputClass} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                        </Field>
                    </div>

                    <Field label="Description" required error={form.errors.description}>
                        <input className={inputClass} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>

                    {entryType === 'adjustment' && (
                        <Field label="Reason" error={form.errors.reason}>
                            <input className={inputClass} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                        </Field>
                    )}
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Saving...' : 'Record entry'}
                    </button>
                    <Link href="/portal/transactions" className="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</Link>
                </div>
            </form>
        </PortalLayout>
    );
}
