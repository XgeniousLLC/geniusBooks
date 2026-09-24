import { Field, inputClass } from '@/components/FormControls';
import PortalLayout from '@/layouts/PortalLayout';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    settings: {
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        country: string | null;
        currency: string;
        currency_symbol: string | null;
        currency_position: string;
        timezone: string;
        financial_year_start_month: number;
        financial_year_start_day: number;
        logo_url: string | null;
    };
    currencies: string[];
    timezones: string[];
}

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

export default function BusinessSettings({ settings, currencies, timezones }: Props) {
    const form = useForm({
        name: settings.name,
        email: settings.email ?? '',
        phone: settings.phone ?? '',
        address: settings.address ?? '',
        country: settings.country ?? '',
        currency: settings.currency,
        currency_symbol: settings.currency_symbol ?? '',
        currency_position: settings.currency_position ?? 'prefix',
        timezone: settings.timezone,
        financial_year_start_month: settings.financial_year_start_month,
        financial_year_start_day: settings.financial_year_start_day,
        logo: null as File | null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // POST + _method so PHP parses the multipart body (logo upload).
        form.transform((data) => ({ ...data, _method: 'patch' }));
        form.post('/portal/settings/business', { forceFormData: true });
    };

    const errorList = Object.values(form.errors);

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Business settings</h1>
                <p className="text-sm text-slate-500 mt-0.5">Company profile, currency and financial year.</p>
            </div>

            {errorList.length > 0 && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p className="font-medium">Please fix the following:</p>
                    <ul className="mt-1 list-disc list-inside">
                        {errorList.map((error, index) => <li key={index}>{error}</li>)}
                    </ul>
                </div>
            )}

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="flex items-center gap-4">
                        {settings.logo_url ? (
                            <img src={settings.logo_url} alt="Business logo" className="h-14 w-14 rounded-lg object-contain border border-slate-200" />
                        ) : (
                            <div className="h-14 w-14 rounded-lg bg-slate-100 flex items-center justify-center text-xs text-slate-400">Logo</div>
                        )}
                        <div className="flex-1">
                            <label className="block text-sm font-medium text-slate-700 mb-1.5">Business logo</label>
                            <input
                                type="file"
                                accept=".png,.jpg,.jpeg,.svg"
                                onChange={(e) => form.setData('logo', e.target.files?.[0] ?? null)}
                                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            {form.errors.logo && <p className="mt-1 text-xs text-red-500">{form.errors.logo}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <Field label="Business name" required error={form.errors.name}>
                            <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label="Business email" error={form.errors.email}>
                            <input type="email" className={inputClass} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        </Field>
                        <Field label="Phone" error={form.errors.phone}>
                            <input className={inputClass} value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label="Country code" error={form.errors.country}>
                            <input className={inputClass} maxLength={2} value={form.data.country} onChange={(e) => form.setData('country', e.target.value.toUpperCase())} />
                        </Field>
                    </div>

                    <Field label="Address" error={form.errors.address}>
                        <textarea className={inputClass} rows={2} value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                    </Field>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <Field label="Currency" required error={form.errors.currency}>
                            <select className={inputClass} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)}>
                                {currencies.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </Field>
                        <Field label="Currency symbol" error={form.errors.currency_symbol} hint="Optional. Defaults to the currency symbol.">
                            <input className={inputClass} maxLength={10} placeholder="e.g. $" value={form.data.currency_symbol} onChange={(e) => form.setData('currency_symbol', e.target.value)} />
                        </Field>
                        <Field label="Symbol position" required error={form.errors.currency_position}>
                            <select className={inputClass} value={form.data.currency_position} onChange={(e) => form.setData('currency_position', e.target.value)}>
                                <option value="prefix">Before amount ($100.00)</option>
                                <option value="suffix">After amount (100.00 $)</option>
                            </select>
                        </Field>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <Field label="Timezone" required error={form.errors.timezone}>
                            <select className={inputClass} value={form.data.timezone} onChange={(e) => form.setData('timezone', e.target.value)}>
                                {timezones.map((tz) => <option key={tz} value={tz}>{tz}</option>)}
                            </select>
                        </Field>
                        <Field label="Financial year starts" required error={form.errors.financial_year_start_month}>
                            <select className={inputClass} value={form.data.financial_year_start_month} onChange={(e) => form.setData('financial_year_start_month', Number(e.target.value))}>
                                {MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                            </select>
                        </Field>
                        <Field label="Starting day" required error={form.errors.financial_year_start_day}>
                            <input type="number" min={1} max={31} className={inputClass} value={form.data.financial_year_start_day} onChange={(e) => form.setData('financial_year_start_day', Number(e.target.value))} />
                        </Field>
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {form.processing ? 'Saving...' : 'Save settings'}
                </button>
            </form>
        </PortalLayout>
    );
}
