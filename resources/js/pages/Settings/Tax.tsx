import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    settings: {
        tax_registration_number: string | null;
        default_tax_rate: string | number;
        tax_inclusive: boolean;
    };
}

export default function TaxSettings({ settings }: Props) {
    const form = useForm({
        tax_registration_number: settings.tax_registration_number ?? '',
        default_tax_rate: String(settings.default_tax_rate),
        tax_inclusive: settings.tax_inclusive,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch('/portal/settings/tax');
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Tax settings</h1>
                <p className="text-sm text-slate-500 mt-0.5">Configurable rates — no country rules are hard-coded.</p>
            </div>

            <form onSubmit={submit} className="max-w-xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <Field label="Tax registration number" error={form.errors.tax_registration_number}>
                        <input className={inputClass} value={form.data.tax_registration_number} onChange={(e) => form.setData('tax_registration_number', e.target.value)} />
                    </Field>
                    <Field label="Default tax rate (%)" required error={form.errors.default_tax_rate}>
                        <input type="number" min="0" max="100" step="0.01" className={inputClass} value={form.data.default_tax_rate} onChange={(e) => form.setData('default_tax_rate', e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-3 text-sm text-slate-700">
                        <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked={form.data.tax_inclusive} onChange={(e) => form.setData('tax_inclusive', e.target.checked)} />
                        Prices are tax-inclusive by default
                    </label>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {form.processing ? 'Saving...' : 'Save settings'}
                </button>
            </form>
        </PortalLayout>
    );
}
