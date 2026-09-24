import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Customer {
    id: number;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    tax_id: string | null;
    payment_terms_days: number | null;
    notes: string | null;
    is_active: boolean;
}

interface Props {
    customer: Customer | null;
    companyCurrency: string;
    paymentTerms: number[];
}

export default function CustomerForm({ customer, companyCurrency, paymentTerms }: Props) {
    const editing = Boolean(customer);

    const form = useForm({
        name: customer?.name ?? '',
        company_name: customer?.company_name ?? '',
        email: customer?.email ?? '',
        phone: customer?.phone ?? '',
        billing_address: customer?.billing_address ?? '',
        shipping_address: customer?.shipping_address ?? '',
        tax_id: customer?.tax_id ?? '',
        payment_terms_days: customer?.payment_terms_days ?? 15,
        notes: customer?.notes ?? '',
        is_active: customer?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing && customer) {
            form.patch(`/portal/customers/${customer.id}`);
        } else {
            form.post('/portal/customers');
        }
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{editing ? 'Edit customer' : 'New customer'}</h1>
                <p className="text-sm text-slate-500 mt-0.5">
                    {editing ? 'Update customer details.' : 'Add a customer to your directory.'}
                </p>
            </div>

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Customer name" required error={form.errors.name}>
                            <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                        </Field>
                        <Field label="Company name" error={form.errors.company_name}>
                            <input className={inputClass} value={form.data.company_name} onChange={(e) => form.setData('company_name', e.target.value)} />
                        </Field>
                        <Field label="Email" error={form.errors.email}>
                            <input type="email" className={inputClass} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        </Field>
                        <Field label="Phone" error={form.errors.phone}>
                            <input className={inputClass} value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label="Tax ID" error={form.errors.tax_id}>
                            <input className={inputClass} value={form.data.tax_id} onChange={(e) => form.setData('tax_id', e.target.value)} />
                        </Field>
                        <Field label="Currency" hint="Locked to your business currency.">
                            <input className={`${inputClass} bg-slate-100`} value={companyCurrency} readOnly />
                        </Field>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Billing address" error={form.errors.billing_address}>
                            <textarea className={inputClass} rows={3} value={form.data.billing_address} onChange={(e) => form.setData('billing_address', e.target.value)} />
                        </Field>
                        <Field label="Shipping address" error={form.errors.shipping_address}>
                            <textarea className={inputClass} rows={3} value={form.data.shipping_address} onChange={(e) => form.setData('shipping_address', e.target.value)} />
                        </Field>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Payment terms" error={form.errors.payment_terms_days}>
                            <select
                                className={inputClass}
                                value={form.data.payment_terms_days}
                                onChange={(e) => form.setData('payment_terms_days', Number(e.target.value))}
                            >
                                {paymentTerms.map((days) => (
                                    <option key={days} value={days}>{days === 0 ? 'Due on receipt' : `Net ${days}`}</option>
                                ))}
                            </select>
                        </Field>
                        <div className="flex items-end">
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
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <textarea className={inputClass} rows={3} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                </div>

                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create customer'}
                    </button>
                    <Link href="/portal/customers" className="text-sm font-medium text-slate-500 hover:text-slate-700">
                        Cancel
                    </Link>
                </div>
            </form>
        </PortalLayout>
    );
}
