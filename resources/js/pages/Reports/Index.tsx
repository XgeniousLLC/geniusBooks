import PortalLayout from '@/layouts/PortalLayout';
import { Link } from '@inertiajs/react';

interface Props {
    reports: { key: string; title: string; description: string }[];
}

export default function ReportsIndex({ reports }: Props) {
    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Reports</h1>
                <p className="text-sm text-slate-500 mt-0.5">Financial summaries derived from your invoices, expenses and ledger.</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {reports.map((report) => (
                    <Link
                        key={report.key}
                        href={`/portal/reports/${report.key}`}
                        className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 transition-colors"
                    >
                        <p className="text-sm font-semibold text-slate-800">{report.title}</p>
                        <p className="mt-1 text-xs text-slate-500">{report.description}</p>
                    </Link>
                ))}
            </div>
        </PortalLayout>
    );
}
