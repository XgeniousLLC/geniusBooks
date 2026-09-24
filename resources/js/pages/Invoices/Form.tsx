import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { computeTotals, formatMinor, type LineInput } from '@/lib/invoiceTotals';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface LineItem {
    product_id: number | '';
    description: string;
    quantity: string;
    unit_price: string;
    discount_type: string;
    discount_value: string;
    tax_rate: string;
}

interface ProductOption {
    id: number;
    name: string;
    unit_price: string;
    tax_rate: number | null;
}

interface Props {
    invoice: {
        id: number;
        number: string;
        customer_id: number;
        issue_date: string;
        due_date: string;
        discount_type: string | null;
        discount_value: string | number | null;
        notes: string | null;
        terms: string | null;
        items: {
            product_id: number | null;
            description: string;
            quantity: number;
            unit_price: string;
            discount_type: string | null;
            discount_value: string | number | null;
            tax_rate: string | number | null;
        }[];
    } | null;
    customers: { id: number; name: string }[];
    products: ProductOption[];
    currency: string;
    taxInclusive: boolean;
    nextNumber: string;
    defaults: { issue_date: string; due_date: string };
}

function blankLine(): LineItem {
    return { product_id: '', description: '', quantity: '1', unit_price: '0.00', discount_type: '', discount_value: '', tax_rate: '' };
}

export default function InvoiceForm({ invoice, customers, products, currency, taxInclusive, nextNumber, defaults }: Props) {
    const editing = Boolean(invoice);

    const form = useForm({
        customer_id: (invoice?.customer_id ?? '') as number | '',
        issue_date: invoice?.issue_date ?? defaults.issue_date,
        due_date: invoice?.due_date ?? defaults.due_date,
        discount_type: invoice?.discount_type ?? '',
        discount_value: invoice?.discount_value != null ? String(invoice.discount_value) : '',
        notes: invoice?.notes ?? '',
        terms: invoice?.terms ?? 'Payment due within 15 days.',
        items: (invoice?.items?.map((item) => ({
            product_id: item.product_id ?? '',
            description: item.description,
            quantity: String(item.quantity),
            unit_price: item.unit_price,
            discount_type: item.discount_type ?? '',
            discount_value: item.discount_value != null ? String(item.discount_value) : '',
            tax_rate: item.tax_rate != null ? String(item.tax_rate) : '',
        })) ?? [blankLine()]) as LineItem[],
    });

    const totals = computeTotals(
        form.data.items as unknown as LineInput[],
        { type: form.data.discount_type, value: form.data.discount_value },
        taxInclusive,
    );

    const updateLine = (index: number, patch: Partial<LineItem>) => {
        const items = form.data.items.map((item, i) => (i === index ? { ...item, ...patch } : item));
        form.setData('items', items);
    };

    const applyProduct = (index: number, productId: string) => {
        const product = products.find((p) => String(p.id) === productId);
        updateLine(index, {
            product_id: productId === '' ? '' : Number(productId),
            description: product ? product.name : form.data.items[index].description,
            unit_price: product ? product.unit_price : form.data.items[index].unit_price,
            tax_rate: product && product.tax_rate != null ? String(product.tax_rate) : '',
        });
    };

    const addLine = () => form.setData('items', [...form.data.items, blankLine()]);
    const removeLine = (index: number) => {
        if (form.data.items.length === 1) {
            return;
        }
        form.setData('items', form.data.items.filter((_, i) => i !== index));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing && invoice) {
            form.put(`/portal/invoices/${invoice.id}`);
        } else {
            form.post('/portal/invoices');
        }
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{editing ? `Edit ${invoice?.number}` : 'New invoice'}</h1>
                <p className="text-sm text-slate-500 mt-0.5">
                    {editing ? 'Only draft invoices can be edited.' : `Next number: ${nextNumber}`}
                </p>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <Field label="Customer" required error={form.errors.customer_id}>
                        <select className={inputClass} value={form.data.customer_id} onChange={(e) => form.setData('customer_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">Select a customer</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Issue date" required error={form.errors.issue_date}>
                        <input type="date" className={inputClass} value={form.data.issue_date} onChange={(e) => form.setData('issue_date', e.target.value)} />
                    </Field>
                    <Field label="Due date" required error={form.errors.due_date}>
                        <input type="date" className={inputClass} value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                    </Field>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                        <h2 className="text-sm font-semibold text-slate-800">Line items</h2>
                        <button type="button" onClick={addLine} className="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            + Add line
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                    <th className="px-4 py-2 font-medium">Item</th>
                                    <th className="px-4 py-2 font-medium min-w-[160px]">Description</th>
                                    <th className="px-4 py-2 font-medium w-20">Qty</th>
                                    <th className="px-4 py-2 font-medium w-28">Unit price</th>
                                    <th className="px-4 py-2 font-medium w-32">Discount</th>
                                    <th className="px-4 py-2 font-medium w-20">Tax %</th>
                                    <th className="px-4 py-2 font-medium w-24 text-right">Line total</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {form.data.items.map((item, index) => (
                                    <tr key={index}>
                                        <td className="px-4 py-2 min-w-[160px]">
                                            <select
                                                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm"
                                                value={item.product_id}
                                                onChange={(e) => applyProduct(index, e.target.value)}
                                            >
                                                <option value="">Custom</option>
                                                {products.map((p) => (
                                                    <option key={p.id} value={p.id}>{p.name}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-4 py-2">
                                            <input
                                                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm"
                                                value={item.description}
                                                onChange={(e) => updateLine(index, { description: e.target.value })}
                                            />
                                            {form.errors[`items.${index}.description`] && (
                                                <p className="mt-1 text-xs text-red-500">{form.errors[`items.${index}.description`]}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-2">
                                            <input type="number" min="0" step="0.01" className="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm" value={item.quantity} onChange={(e) => updateLine(index, { quantity: e.target.value })} />
                                        </td>
                                        <td className="px-4 py-2">
                                            <input type="number" min="0" step="0.01" className="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm" value={item.unit_price} onChange={(e) => updateLine(index, { unit_price: e.target.value })} />
                                        </td>
                                        <td className="px-4 py-2">
                                            <div className="flex gap-1">
                                                <select className="rounded-lg border border-slate-200 bg-slate-50 px-1 py-1.5 text-xs" value={item.discount_type} onChange={(e) => updateLine(index, { discount_type: e.target.value })}>
                                                    <option value="">None</option>
                                                    <option value="percent">%</option>
                                                    <option value="fixed">Fixed</option>
                                                </select>
                                                {item.discount_type && (
                                                    <input type="number" min="0" step="0.01" className="w-16 rounded-lg border border-slate-200 bg-slate-50 px-1 py-1.5 text-xs" value={item.discount_value} onChange={(e) => updateLine(index, { discount_value: e.target.value })} />
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-2">
                                            <input type="number" min="0" max="100" step="0.01" className="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm" value={item.tax_rate} onChange={(e) => updateLine(index, { tax_rate: e.target.value })} />
                                        </td>
                                        <td className="px-4 py-2 text-right font-medium text-slate-700">
                                            {formatMinor(totals.lines[index]?.total ?? 0, currency)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <button type="button" onClick={() => removeLine(index)} disabled={form.data.items.length === 1} className="text-xs text-red-500 hover:text-red-700 disabled:opacity-30">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {form.errors.items && <p className="px-5 py-2 text-xs text-red-500">{form.errors.items}</p>}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <Field label="Invoice discount" error={form.errors.discount_value} hint="Applied after line discounts.">
                                <div className="flex gap-2">
                                    <select className={inputClass} value={form.data.discount_type} onChange={(e) => form.setData('discount_type', e.target.value)}>
                                        <option value="">None</option>
                                        <option value="percent">Percent</option>
                                        <option value="fixed">Fixed</option>
                                    </select>
                                    {form.data.discount_type && (
                                        <input type="number" min="0" step="0.01" className={inputClass} value={form.data.discount_value} onChange={(e) => form.setData('discount_value', e.target.value)} />
                                    )}
                                </div>
                            </Field>
                        </div>
                        <Field label="Notes" error={form.errors.notes}>
                            <textarea className={inputClass} rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        </Field>
                        <Field label="Terms" error={form.errors.terms}>
                            <textarea className={inputClass} rows={2} value={form.data.terms} onChange={(e) => form.setData('terms', e.target.value)} />
                        </Field>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                        <h2 className="text-sm font-semibold text-slate-800 mb-4">Summary</h2>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-slate-500">Subtotal</dt>
                                <dd className="font-medium text-slate-800">{formatMinor(totals.subtotal, currency)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-500">Discount</dt>
                                <dd className="font-medium text-slate-800">-{formatMinor(totals.discount, currency)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-500">{taxInclusive ? 'Tax (included)' : 'Tax'}</dt>
                                <dd className="font-medium text-slate-800">{formatMinor(totals.tax, currency)}</dd>
                            </div>
                            <div className="flex justify-between border-t border-slate-100 pt-3">
                                <dt className="font-semibold text-slate-700">Total</dt>
                                <dd className="font-bold text-slate-900">{formatMinor(totals.total, currency)}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Saving...' : editing ? 'Save changes' : 'Create draft invoice'}
                    </button>
                    <Link href="/portal/invoices" className="text-sm font-medium text-slate-500 hover:text-slate-700">
                        Cancel
                    </Link>
                </div>
            </form>
        </PortalLayout>
    );
}
