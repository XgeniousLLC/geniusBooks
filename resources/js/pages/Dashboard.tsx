import PortalLayout from '@/layouts/PortalLayout';
import { Link, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props;
    const name = auth?.user?.name ?? 'there';

    return (
        <PortalLayout>
            {/* Page header */}
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Welcome, {name}</h1>
                <p className="text-sm text-slate-500 mt-0.5">You are signed in to your account</p>
            </div>

            {/* Quick actions */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Link
                    href="/portal/profile"
                    className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4 hover:border-indigo-300 transition-colors"
                >
                    <div className="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8}
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-slate-800">Profile</p>
                        <p className="text-xs text-slate-500">Update your account information</p>
                    </div>
                </Link>
            </div>
        </PortalLayout>
    );
}
