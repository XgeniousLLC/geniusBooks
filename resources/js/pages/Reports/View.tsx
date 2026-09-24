import PortalLayout from '@/layouts/PortalLayout';
import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface Stat {
    label: string;
    display: string;
    emphasis?: boolean;
}

interface Row {
    label: string;
    display: string;
    meta?: string;
}

interface Section {
    heading: string;
    rows: Row[];
    total_display: string;
}

interface Report {
    key: string;
    title: string;
    period: { from: string; to: string };
    summary: Stat[];
    sections: Section[];
}

interface Props {
    report: Report;
}

export default function ReportView({ report }: Props) {
    const [from, setFrom] = useState(report.period.from);
    const [to, setTo] = useState(report.period.to);

    const basePath = window.location.pathname;

    const apply = (e: FormEvent) => {
        e.preventDefault();
        router.get(basePath, { from, to }, { preserveState: true, preserveScroll: true });
    };

    const exportUrl = (format: string) => `${basePath}?from=${from}&to=${to}&format=${format}`;

    return (
        <PortalLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">{report.title}</h1>
                    <p className="text-sm text-slate-500 mt-0.5">{report.period.from} – {report.period.to}</p>
                </div>
                <div className="flex gap-2">
                    <a href={exportUrl('csv')} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href={exportUrl('pdf')} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>

            <form onSubmit={apply} className="mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">From</label>
                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">To</label>
                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <button type="submit" className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Apply</button>
                <Link href="/portal/reports" className="text-sm font-medium text-slate-500 hover:text-slate-700">All reports</Link>
            </form>

            <div className="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                {report.summary.map((stat) => (
                    <div key={stat.label} className={`rounded-xl border shadow-sm p-5 ${stat.emphasis ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-white'}`}>
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{stat.label}</p>
                        <p className="mt-1 text-2xl font-bold text-slate-900">{stat.display}</p>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {report.sections.map((section) => (
                    <div key={section.heading} className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h2 className="text-sm font-semibold text-slate-800">{section.heading}</h2>
                        </div>
                        <table className="min-w-full text-sm">
                            <tbody className="divide-y divide-slate-100">
                                {section.rows.length === 0 ? (
                                    <tr><td className="px-5 py-6 text-slate-500">No data for this period.</td></tr>
                                ) : (
                                    section.rows.map((row, index) => (
                                        <tr key={`${row.label}-${index}`}>
                                            <td className="px-5 py-3 text-slate-700">
                                                {row.label}
                                                {row.meta && <span className="ml-2 text-xs text-slate-400">{row.meta}</span>}
                                            </td>
                                            <td className="px-5 py-3 text-right font-medium text-slate-800">{row.display}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-slate-100">
                                    <td className="px-5 py-3 font-semibold text-slate-700">Total</td>
                                    <td className="px-5 py-3 text-right font-bold text-slate-900">{section.total_display}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                ))}
            </div>
        </PortalLayout>
    );
}
