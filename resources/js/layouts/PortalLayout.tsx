import { Link, router, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';
import { type ReactNode, useState } from 'react';
import Toast from '@/components/Toast';

const NAV = [
    {
        href: '/portal',
        label: 'Dashboard',
        icon: (
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8}
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
        ),
        match: (p: string) => p === '/portal',
    },
    {
        href: '/portal/profile',
        label: 'Profile',
        icon: (
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8}
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        ),
        match: (p: string) => p.startsWith('/portal/profile'),
    },
];

export default function PortalLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const currentPath = window.location.pathname;

    const initials = auth?.user?.name
        ? auth.user.name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
        : '?';

    return (
        <div className="h-screen overflow-hidden bg-slate-50 flex">

            {/* Mobile overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 bg-black/40 z-20 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* ── Sidebar ─────────────────────────────────────────────────── */}
            <aside className={`
                fixed inset-y-0 left-0 z-30 w-64 bg-slate-900 flex flex-col h-full
                transform transition-transform duration-200 ease-in-out
                lg:translate-x-0 lg:static lg:z-auto lg:shrink-0
                ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
            `}>
                {/* Logo */}
                <div className="flex items-center gap-3 px-5 h-16 border-b border-slate-700/50 shrink-0">
                    <div className="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center shrink-0">
                        <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <span className="text-white font-semibold text-sm">Support Portal</span>
                </div>

                {/* Nav */}
                <nav className="flex-1 px-3 py-4 overflow-y-auto space-y-0.5">
                    <p className="text-slate-500 text-[10px] font-semibold uppercase tracking-widest px-3 mb-2">
                        Menu
                    </p>
                    {NAV.map((item) => {
                        const active = item.match(currentPath);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={() => setSidebarOpen(false)}
                                className={`
                                    flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                                    transition-colors duration-150 group
                                    ${active
                                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-900'
                                        : item.highlight
                                            ? 'border border-indigo-500/40 text-indigo-300 hover:bg-indigo-600/20'
                                            : 'text-slate-400 hover:text-white hover:bg-slate-800'
                                    }
                                `}
                            >
                                <span className={`shrink-0 ${active ? 'text-white' : item.highlight ? 'text-indigo-400' : 'text-slate-500 group-hover:text-slate-300'}`}>
                                    {item.icon}
                                </span>
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                {/* User footer */}
                <div className="border-t border-slate-700/50 p-4 shrink-0">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center shrink-0">
                            <span className="text-white text-xs font-bold">{initials}</span>
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-white truncate">{auth?.user?.name}</p>
                            <p className="text-xs text-slate-500 truncate">{auth?.user?.email}</p>
                        </div>
                        <button
                            onClick={() => router.post('/portal/logout')}
                            title="Logout"
                            className="text-slate-500 hover:text-red-400 transition-colors shrink-0"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>
                </div>
            </aside>

            {/* ── Main area ───────────────────────────────────────────────── */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden">

                {/* Top bar (mobile + page title) */}
                <header className="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 shrink-0">
                    <div className="flex items-center gap-3">
                        {/* Mobile hamburger */}
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden text-slate-500 hover:text-slate-700"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        {/* Breadcrumb path hint */}
                        <span className="text-sm text-slate-400 hidden sm:block">
                            {NAV.find(n => n.match(currentPath))?.label ?? 'Portal'}
                        </span>
                    </div>
                </header>

                {/* Page content — scrolls only inside */}
                <main className="flex-1 overflow-y-auto p-4 sm:p-6 min-h-0">
                    {children}
                </main>
            </div>

            <Toast />
        </div>
    );
}
