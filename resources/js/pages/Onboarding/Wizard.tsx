import { useForm, Link } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useState } from 'react';

interface Props {
    currencies: string[];
    paymentTerms: number[];
    timezones: string[];
    defaults: Record<string, string | number | boolean>;
    adding?: boolean;
    submitUrl: string;
}

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

export default function OnboardingWizard({ currencies, paymentTerms, timezones, defaults, adding = false, submitUrl }: Props) {
    const [step, setStep] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        address: '',
        country: '',
        currency: String(defaults.currency ?? 'USD'),
        timezone: String(defaults.timezone ?? 'UTC'),
        financial_year_start_month: Number(defaults.financial_year_start_month ?? 1),
        financial_year_start_day: Number(defaults.financial_year_start_day ?? 1),
        tax_registration_number: '',
        tax_inclusive: Boolean(defaults.tax_inclusive ?? false),
        default_tax_rate: Number(defaults.default_tax_rate ?? 0),
        invoice_prefix: String(defaults.invoice_prefix ?? 'INV-'),
        invoice_number_padding: Number(defaults.invoice_number_padding ?? 4),
        default_payment_terms_days: Number(defaults.default_payment_terms_days ?? 15),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (step < 2) {
            setStep(step + 1);
            return;
        }
        post(submitUrl);
    };

    const inputClass =
        'w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900 ' +
        'placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent ' +
        'focus:bg-white transition-all';

    const err = (key: keyof typeof data) =>
        errors[key] ? <p className="mt-1 text-xs text-red-500">{errors[key]}</p> : null;

    return (
        <div className="min-h-screen bg-slate-50 py-10 px-4">
            <div className="max-w-2xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-slate-900">{adding ? 'Add a new business' : 'Set up your business'}</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Step {step + 1} of 3 — this takes less than a minute.
                    </p>
                </div>

                <div className="flex gap-2 mb-6">
                    {[0, 1, 2].map((i) => (
                        <div
                            key={i}
                            className={`h-1.5 flex-1 rounded-full ${i <= step ? 'bg-indigo-600' : 'bg-slate-200'}`}
                        />
                    ))}
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                    {step === 0 && (
                        <>
                            <Field label="Business name" required>
                                <input
                                    className={inputClass}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="ABC Digital Agency"
                                    autoFocus
                                    required
                                />
                                {err('name')}
                            </Field>
                            <Field label="Business email">
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="hello@abc.test"
                                />
                                {err('email')}
                            </Field>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Phone">
                                    <input
                                        className={inputClass}
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                    {err('phone')}
                                </Field>
                                <Field label="Country code">
                                    <input
                                        className={inputClass}
                                        value={data.country}
                                        maxLength={2}
                                        onChange={(e) => setData('country', e.target.value.toUpperCase())}
                                        placeholder="US"
                                    />
                                    {err('country')}
                                </Field>
                            </div>
                            <Field label="Address">
                                <textarea
                                    className={inputClass}
                                    rows={2}
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                />
                                {err('address')}
                            </Field>
                        </>
                    )}

                    {step === 1 && (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Currency" required>
                                    <select
                                        className={inputClass}
                                        value={data.currency}
                                        onChange={(e) => setData('currency', e.target.value)}
                                    >
                                        {currencies.map((c) => (
                                            <option key={c} value={c}>{c}</option>
                                        ))}
                                    </select>
                                    {err('currency')}
                                </Field>
                                <Field label="Timezone" required>
                                    <select
                                        className={inputClass}
                                        value={data.timezone}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                    >
                                        {timezones.map((tz) => (
                                            <option key={tz} value={tz}>{tz}</option>
                                        ))}
                                    </select>
                                    {err('timezone')}
                                </Field>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Financial year starts" required>
                                    <select
                                        className={inputClass}
                                        value={data.financial_year_start_month}
                                        onChange={(e) => setData('financial_year_start_month', Number(e.target.value))}
                                    >
                                        {MONTHS.map((m, i) => (
                                            <option key={m} value={i + 1}>{m}</option>
                                        ))}
                                    </select>
                                    {err('financial_year_start_month')}
                                </Field>
                                <Field label="Starting day" required>
                                    <input
                                        type="number"
                                        min={1}
                                        max={31}
                                        className={inputClass}
                                        value={data.financial_year_start_day}
                                        onChange={(e) => setData('financial_year_start_day', Number(e.target.value))}
                                    />
                                    {err('financial_year_start_day')}
                                </Field>
                            </div>
                        </>
                    )}

                    {step === 2 && (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Default tax rate (%)" required>
                                    <input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        className={inputClass}
                                        value={data.default_tax_rate}
                                        onChange={(e) => setData('default_tax_rate', Number(e.target.value))}
                                    />
                                    {err('default_tax_rate')}
                                </Field>
                                <Field label="Tax registration number">
                                    <input
                                        className={inputClass}
                                        value={data.tax_registration_number}
                                        onChange={(e) => setData('tax_registration_number', e.target.value)}
                                    />
                                    {err('tax_registration_number')}
                                </Field>
                            </div>
                            <label className="flex items-center gap-3 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    className="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    checked={data.tax_inclusive}
                                    onChange={(e) => setData('tax_inclusive', e.target.checked)}
                                />
                                Prices are tax-inclusive by default
                            </label>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <Field label="Invoice prefix" required>
                                    <input
                                        className={inputClass}
                                        value={data.invoice_prefix}
                                        onChange={(e) => setData('invoice_prefix', e.target.value)}
                                    />
                                    {err('invoice_prefix')}
                                </Field>
                                <Field label="Number padding" required>
                                    <input
                                        type="number"
                                        min={1}
                                        max={10}
                                        className={inputClass}
                                        value={data.invoice_number_padding}
                                        onChange={(e) => setData('invoice_number_padding', Number(e.target.value))}
                                    />
                                    {err('invoice_number_padding')}
                                </Field>
                                <Field label="Payment terms (days)" required>
                                    <select
                                        className={inputClass}
                                        value={data.default_payment_terms_days}
                                        onChange={(e) => setData('default_payment_terms_days', Number(e.target.value))}
                                    >
                                        {paymentTerms.map((d) => (
                                            <option key={d} value={d}>{d === 0 ? 'Due on receipt' : `Net ${d}`}</option>
                                        ))}
                                    </select>
                                    {err('default_payment_terms_days')}
                                </Field>
                            </div>
                        </>
                    )}

                    <div className="flex items-center justify-between pt-2">
                        <div className="flex items-center gap-4">
                            <button
                                type="button"
                                onClick={() => setStep(Math.max(0, step - 1))}
                                disabled={step === 0}
                                className="text-sm font-medium text-slate-500 hover:text-slate-700 disabled:opacity-0"
                            >
                                Back
                            </button>
                            {adding && (
                                <Link href="/portal" className="text-sm font-medium text-slate-500 hover:text-slate-700">
                                    Cancel
                                </Link>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold
                                       py-2.5 px-5 rounded-xl focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                                       disabled:opacity-50 transition-colors"
                        >
                            {step < 2 ? 'Continue' : processing ? 'Creating...' : 'Create business'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function Field({ label, required, children }: { label: string; required?: boolean; children: ReactNode }) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
                {label}
                {required && <span className="text-red-500"> *</span>}
            </label>
            {children}
        </div>
    );
}
