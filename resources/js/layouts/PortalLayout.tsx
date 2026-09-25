import { Link, router, usePage } from '@inertiajs/react';
import { type CompanyOption, type PageProps } from '@/types';
import { type ReactNode, useState } from 'react';
import Toast from '@/components/Toast';

interface NavItem {
    label: string;
    href?: string;
    roles?: string[];
    match?: (path: string) => boolean;
}

interface NavGroup {
    label: string | null;
    items: NavItem[];
}

const NAV_GROUPS: NavGroup[] = [
    {
        label: null,
        items: [
            {
                label: 'Dashboard',
                href: '/portal',
                match: (p) => p === '/portal',
            },
        ],
    },
    {
        label: 'Sales',
        items: [
            {
                label: 'Customers',
                href: '/portal/customers',
                roles: ['owner', 'accountant', 'staff'],
                match: (p) => p.startsWith('/portal/customers'),
            },
            {
                label: 'Products & Services',
                href: '/portal/products',
                roles: ['owner', 'accountant', 'staff'],
                match: (p) => p.startsWith('/portal/products'),
            },
            {
                label: 'Invoices',
                href: '/portal/invoices',
                roles: ['owner', 'accountant', 'staff'],
                match: (p) => p.startsWith('/portal/invoices'),
            },
            {
                label: 'Quotes',
                href: '/portal/quotes',
                roles: ['owner', 'accountant', 'staff'],
                match: (p) => p.startsWith('/portal/quotes'),
            },
            {
                label: 'Payments',
                href: '/portal/payments',
                roles: ['owner', 'accountant', 'staff'],
                match: (p) => p.startsWith('/portal/payments'),
            },
        ],
    },
    {
        label: 'Expenses',
        items: [
            {
                label: 'Expenses',
                href: '/portal/expenses',
                roles: ['owner', 'accountant', 'staff'],
                match: (p) => p.startsWith('/portal/expenses'),
            },
            {
                label: 'Vendors',
                href: '/portal/vendors',
                roles: ['owner', 'accountant', 'staff'],
                match: (p) => p.startsWith('/portal/vendors'),
            },
        ],
    },
    {
        label: 'Accounting',
        items: [
            {
                label: 'Transactions',
                href: '/portal/transactions',
                roles: ['owner', 'accountant'],
                match: (p) => p.startsWith('/portal/transactions'),
            },
            {
                label: 'Chart of Accounts',
                href: '/portal/chart-of-accounts',
                roles: ['owner', 'accountant'],
                match: (p) => p.startsWith('/portal/chart-of-accounts'),
            },
            {
                label: 'Accounts',
                href: '/portal/accounts',
                roles: ['owner', 'accountant'],
                match: (p) => p.startsWith('/portal/accounts'),
            },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'Profit & Loss', href: '/portal/reports/profit-and-loss', roles: ['owner', 'accountant'], match: (p) => p === '/portal/reports/profit-and-loss' },
            { label: 'Income', href: '/portal/reports/income', roles: ['owner', 'accountant'], match: (p) => p === '/portal/reports/income' },
            { label: 'Expenses', href: '/portal/reports/expenses', roles: ['owner', 'accountant'], match: (p) => p === '/portal/reports/expenses' },
            { label: 'Receivables', href: '/portal/reports/receivables', roles: ['owner', 'accountant'], match: (p) => p === '/portal/reports/receivables' },
            { label: 'Tax Summary', href: '/portal/reports/tax-summary', roles: ['owner', 'accountant'], match: (p) => p === '/portal/reports/tax-summary' },
            { label: 'General Ledger', href: '/portal/reports/general-ledger', roles: ['owner', 'accountant'], match: (p) => p.startsWith('/portal/reports/general-ledger') },
        ],
    },
    {
        label: 'Settings',
        items: [
            { label: 'Business', href: '/portal/settings/business', roles: ['owner'], match: (p) => p.startsWith('/portal/settings/business') },
            {
                label: 'Users & Roles',
                href: '/portal/settings/users',
                roles: ['owner'],
                match: (p) => p.startsWith('/portal/settings/users'),
            },
            { label: 'Invoice Settings', href: '/portal/settings/invoices', roles: ['owner'], match: (p) => p.startsWith('/portal/settings/invoices') },
            { label: 'Tax Settings', href: '/portal/settings/tax', roles: ['owner'], match: (p) => p.startsWith('/portal/settings/tax') },
            { label: 'Online Payments', href: '/portal/settings/payments', roles: ['owner'], match: (p) => p.startsWith('/portal/settings/payments') },
            {
                label: 'Email Settings',
                href: '/portal/settings/email',
                roles: ['owner'],
                match: (p) => p.startsWith('/portal/settings/email'),
            },
            {
                label: 'Profile',
                href: '/portal/profile',
                match: (p) => p.startsWith('/portal/profile'),
            },
        ],
    },
];

function CompanySwitcher({ companies, current }: { companies: CompanyOption[]; current?: { id: number; name: string } | null }) {
    const [open, setOpen] = useState(false);

    if (!current) {
        return null;
    }

    return (
        <div className="relative border-b border-slate-700/50">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-haspopup="listbox"
                aria-expanded={open}
                className="w-full h-16 px-5 flex items-center justify-between gap-2 text-left hover:bg-slate-800 transition-colors"
            >
                <span className="min-w-0">
                    <span className="block text-[10px] uppercase tracking-widest text-slate-500">Business</span>
                    <span className="block text-white font-semibold text-sm truncate">{current.name}</span>
                </span>
                <svg className={`w-4 h-4 text-slate-500 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {open && (
                <ul
                    role="listbox"
                    className="absolute inset-x-2 top-[68px] z-40 bg-slate-800 rounded-xl border border-slate-700 shadow-xl py-1"
                >
                    {companies.map((c) => (
                        <li key={c.id} role="option" aria-selected={c.id === current.id}>
                            <button
                                type="button"
                                disabled={c.id === current.id}
                                onClick={() => {
                                    setOpen(false);
                                    router.post('/portal/company/switch', { company_id: c.id });
                                }}
                                className={`w-full text-left px-3 py-2 text-sm rounded-lg transition-colors ${
                                    c.id === current.id
                                        ? 'text-indigo-300 font-medium'
                                        : 'text-slate-300 hover:bg-slate-700 hover:text-white'
                                }`}
                            >
                                {c.name}
                            </button>
                        </li>
                    ))}
                    <li className="border-t border-slate-700 mt-1 pt-1">
                        <Link
                            href="/portal/businesses/create"
                            onClick={() => setOpen(false)}
                            className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-indigo-300 hover:bg-slate-700 hover:text-white"
                        >
                            <span className="text-base leading-none">+</span> New business
                        </Link>
                    </li>
                </ul>
            )}
        </div>
    );
}

export default function PortalLayout({ children }: { children: ReactNode }) {
    const { auth, currentCompany, companies, impersonating } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const currentPath = window.location.pathname;

    const roles = auth?.roles ?? [];
    const companyList = companies ?? [];

    const initials = auth?.user?.name
        ? auth.user.name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
        : '?';

    return (
        <div className="h-screen overflow-hidden bg-slate-50 flex">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-lg focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:text-white"
            >
                Skip to content
            </a>
            {sidebarOpen && (
                <div
                    className="fixed inset-0 bg-black/40 z-20 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            <aside className={`
                fixed inset-y-0 left-0 z-30 w-64 bg-slate-900 flex flex-col h-full
                transform transition-transform duration-200 ease-in-out
                lg:translate-x-0 lg:static lg:z-auto lg:shrink-0
                ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
            `}>
                <CompanySwitcher companies={companyList} current={currentCompany} />

                <nav className="flex-1 px-3 py-4 overflow-y-auto space-y-4">
                    {NAV_GROUPS.map((group, gi) => {
                        const items = group.items.filter(
                            (item) => !item.roles || item.roles.some((r) => roles.includes(r))
                        );

                        if (items.length === 0) {
                            return null;
                        }

                        return (
                            <div key={gi}>
                                {group.label && (
                                    <p className="text-slate-500 text-[10px] font-semibold uppercase tracking-widest px-3 mb-1.5">
                                        {group.label}
                                    </p>
                                )}
                                <ul className="space-y-0.5">
                                    {items.map((item) => {
                                        const available = Boolean(item.href);
                                        const active = item.match ? item.match(currentPath) : false;

                                        if (!available) {
                                            return (
                                                <li key={item.label}>
                                                    <span
                                                        aria-disabled="true"
                                                        className="flex items-center justify-between px-3 py-2 rounded-lg text-sm text-slate-600 cursor-not-allowed"
                                                    >
                                                        {item.label}
                                                        <span className="text-[9px] uppercase tracking-wide text-slate-600 border border-slate-700 rounded px-1">Soon</span>
                                                    </span>
                                                </li>
                                            );
                                        }

                                        return (
                                            <li key={item.label}>
                                                <Link
                                                    href={item.href!}
                                                    onClick={() => setSidebarOpen(false)}
                                                    aria-current={active ? 'page' : undefined}
                                                    className={`flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                                                        active
                                                            ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-900'
                                                            : 'text-slate-400 hover:text-white hover:bg-slate-800'
                                                    }`}
                                                >
                                                    {item.label}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        );
                    })}
                </nav>

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
                            aria-label="Logout"
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

            <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
                <header className="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 shrink-0">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Open navigation"
                            className="lg:hidden text-slate-500 hover:text-slate-700"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <span className="text-sm text-slate-400 hidden sm:block">
                            {currentCompany?.name ?? 'Xgenious Accounting'}
                        </span>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const value = new FormData(e.currentTarget).get('q');
                            router.get('/portal/search', { q: String(value ?? '') });
                        }}
                        className="hidden sm:block"
                    >
                        <input
                            type="search"
                            name="q"
                            placeholder="Search…"
                            aria-label="Search"
                            className="w-48 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </form>
                </header>

                <main id="main-content" className="flex-1 overflow-y-auto p-4 sm:p-6 min-h-0">
                    {impersonating && (
                        <div className="mb-4 flex items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3">
                            <p className="text-sm text-amber-800">
                                You are impersonating <strong>{auth?.user?.name}</strong>.
                            </p>
                            <button
                                onClick={() => router.post('/portal/impersonate/stop')}
                                className="text-sm font-semibold text-amber-900 underline"
                            >
                                Stop impersonating
                            </button>
                        </div>
                    )}
                    {children}
                </main>
            </div>

            <Toast />
        </div>
    );
}
