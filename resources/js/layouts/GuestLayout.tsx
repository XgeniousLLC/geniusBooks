import { type ReactNode } from 'react';

export default function GuestLayout({ children, title, subtitle }: {
    children: ReactNode;
    title?: string;
    subtitle?: string;
}) {
    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-slate-100 flex items-center justify-center px-4 py-12">
            {/* Decorative blobs */}
            <div className="fixed top-0 left-0 w-full h-full pointer-events-none overflow-hidden -z-10">
                <div className="absolute -top-40 -right-40 w-96 h-96 bg-indigo-200 rounded-full opacity-30 blur-3xl" />
                <div className="absolute -bottom-40 -left-40 w-96 h-96 bg-violet-200 rounded-full opacity-30 blur-3xl" />
            </div>

            <div className="w-full max-w-md">
                {/* Logo / Brand */}
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 rounded-2xl shadow-lg shadow-indigo-200 mb-4">
                        <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    {title && <h1 className="text-2xl font-bold text-gray-900">{title}</h1>}
                    {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
                </div>

                {/* Card */}
                <div className="bg-white rounded-2xl shadow-xl shadow-slate-200/60 ring-1 ring-slate-100 px-8 py-8">
                    {children}
                </div>

                {/* Footer */}
                <p className="text-center text-xs text-gray-400 mt-6">
                    <a href="/legal/terms" className="hover:underline">Terms</a>
                    <span className="mx-2">·</span>
                    <a href="/legal/privacy" className="hover:underline">Privacy</a>
                </p>
            </div>
        </div>
    );
}
