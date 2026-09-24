import { type ReactNode } from 'react';

export const inputClass =
    'w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 ' +
    'placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent ' +
    'focus:bg-white transition-all';

export function Field({
    label,
    error,
    required,
    hint,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
                {label}
                {required && <span className="text-red-500"> *</span>}
            </label>
            {children}
            {hint && !error && <p className="mt-1 text-xs text-slate-400">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}
