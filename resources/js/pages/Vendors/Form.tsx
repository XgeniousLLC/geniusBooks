import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    vendor: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        tax_id: string | null;
        notes: string | null;
        is_active: boolean;
    } | null;
}

export default function VendorForm({ vendor }: Props) {
    const editing = Boolean(vendor);

    const form = useForm({
        name: vendor?.name ?? '',
        email: vendor?.email ?? '',
        phone: vendor?.phone ?? '',
        address: vendor?.address ?? '',
        tax_id: vendor?.tax_id ?? '',
        notes: vendor?.notes ?? '',
        is_active: vendor?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing && vendor) {
            form.patch(`/portal/vendors/${vendor.id}`);
        } else {
            form.post('/portal/vendors');
        }
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{editing ? 'Edit vendor' : 'New vendor'}</h1>
                <p className="text-sm text-slate-500 mt-0.5">Suppliers and service providers you pay.</p>
            </div>

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Name" required error={form.errors.name}>
                            <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
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
                    </div>
                    <Field label="Address" error={form.errors.address}>
                        <textarea className={inputClass} rows={2} value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                    </Field>
                    <Field label="Notes" error={form.errors.notes}>
                        <textarea className={inputClass} rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-3 text-sm text-slate-700">
                        <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                        Active
                    </label>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create vendor'}
                    </button>
                    <Link href="/portal/vendors" className="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</Link>
                </div>
            </form>
        </PortalLayout>
    );
}
