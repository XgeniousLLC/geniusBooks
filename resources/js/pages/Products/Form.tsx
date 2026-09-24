import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Product {
    id: number;
    name: string;
    sku: string | null;
    description: string | null;
    type: 'product' | 'service';
    unit_price: string;
    tax_rate: string | number | null;
    category: string | null;
    is_active: boolean;
}

interface Props {
    product: Product | null;
    currency: string;
}

export default function ProductForm({ product, currency }: Props) {
    const editing = Boolean(product);

    const form = useForm({
        name: product?.name ?? '',
        sku: product?.sku ?? '',
        description: product?.description ?? '',
        type: product?.type ?? 'service',
        unit_price: product?.unit_price ?? '0.00',
        tax_rate: product?.tax_rate ?? '',
        category: product?.category ?? '',
        is_active: product?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing && product) {
            form.patch(`/portal/products/${product.id}`);
        } else {
            form.post('/portal/products');
        }
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{editing ? 'Edit item' : 'New product or service'}</h1>
                <p className="text-sm text-slate-500 mt-0.5">Reusable pricing for invoices.</p>
            </div>

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Name" required error={form.errors.name}>
                            <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                        </Field>
                        <Field label="SKU" error={form.errors.sku}>
                            <input className={inputClass} value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value)} />
                        </Field>
                        <Field label="Type" required error={form.errors.type}>
                            <select className={inputClass} value={form.data.type} onChange={(e) => form.setData('type', e.target.value as 'product' | 'service')}>
                                <option value="service">Service</option>
                                <option value="product">Product</option>
                            </select>
                        </Field>
                        <Field label="Category" error={form.errors.category}>
                            <input className={inputClass} value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                        </Field>
                        <Field label={`Unit price (${currency})`} required error={form.errors.unit_price}>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className={inputClass}
                                value={form.data.unit_price}
                                onChange={(e) => form.setData('unit_price', e.target.value)}
                            />
                        </Field>
                        <Field label="Tax rate (%)" error={form.errors.tax_rate}>
                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                className={inputClass}
                                value={form.data.tax_rate}
                                onChange={(e) => form.setData('tax_rate', e.target.value)}
                            />
                        </Field>
                    </div>

                    <Field label="Description" error={form.errors.description}>
                        <textarea className={inputClass} rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
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
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create item'}
                    </button>
                    <Link href="/portal/products" className="text-sm font-medium text-slate-500 hover:text-slate-700">
                        Cancel
                    </Link>
                </div>
            </form>
        </PortalLayout>
    );
}
