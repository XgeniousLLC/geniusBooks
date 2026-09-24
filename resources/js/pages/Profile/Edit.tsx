import PortalLayout from '@/layouts/PortalLayout';
import { useForm, router } from '@inertiajs/react';
import { type User } from '@/types';
import { useState } from 'react';

interface Props {
    user: User;
}

type Tab = 'info' | 'password';

const TABS: { id: Tab; label: string; icon: string }[] = [
    {
        id: 'info',
        label: 'Account Information',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
    {
        id: 'password',
        label: 'Change Password',
        icon: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
    },
];

function InputField({
    label, type = 'text', value, onChange, error, placeholder, required = true,
}: {
    label: string; type?: string; value: string;
    onChange: (v: string) => void; error?: string;
    placeholder?: string; required?: boolean;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">{label}</label>
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                required={required}
                className="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-900
                           placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500
                           focus:border-transparent focus:bg-white transition-all duration-150"
            />
            {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
        </div>
    );
}

export default function ProfileEdit({ user }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('info');

    const infoForm = useForm({ name: user.name, email: user.email });
    const pwForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    const initials = user.name
        .split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();

    return (
        <PortalLayout>
            <div className="max-w-xl">
                {/* Page header */}
                <div className="mb-6">
                    <h1 className="text-xl font-bold text-slate-900">Profile</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Manage your account settings</p>
                </div>

                {/* Avatar + name card */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6 flex items-center gap-4">
                    <div className="w-14 h-14 bg-indigo-600 rounded-full flex items-center justify-center shrink-0">
                        <span className="text-white text-lg font-bold">{initials}</span>
                    </div>
                    <div>
                        <p className="text-base font-semibold text-slate-900">{user.name}</p>
                        <p className="text-sm text-slate-500">{user.email}</p>
                    </div>
                </div>

                {/* Tab bar */}
                <div className="flex gap-1 bg-slate-100 rounded-xl p-1 mb-6">
                    {TABS.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`
                                flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium
                                transition-all duration-150
                                ${activeTab === tab.id
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-700'
                                }
                            `}
                        >
                            <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d={tab.icon} />
                            </svg>
                            <span className="hidden sm:inline">{tab.label}</span>
                        </button>
                    ))}
                </div>

                {/* Tab: Account Information */}
                {activeTab === 'info' && (
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h2 className="text-sm font-semibold text-slate-800 mb-5">Account Information</h2>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                infoForm.patch('/portal/profile');
                            }}
                            className="space-y-4"
                        >
                            <InputField
                                label="Full Name"
                                value={infoForm.data.name}
                                onChange={(v) => infoForm.setData('name', v)}
                                error={infoForm.errors.name}
                                placeholder="Your full name"
                            />
                            <InputField
                                label="Email Address"
                                type="email"
                                value={infoForm.data.email}
                                onChange={(v) => infoForm.setData('email', v)}
                                error={infoForm.errors.email}
                                placeholder="you@example.com"
                            />

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={infoForm.processing}
                                    className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                                               text-sm font-semibold px-5 py-2.5 rounded-xl
                                               disabled:opacity-50 transition-colors duration-150"
                                >
                                    {infoForm.processing ? (
                                        <>
                                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Saving...
                                        </>
                                    ) : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab: Change Password */}
                {activeTab === 'password' && (
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h2 className="text-sm font-semibold text-slate-800 mb-1">Change Password</h2>
                        <p className="text-xs text-slate-400 mb-5">Choose a strong password with at least 8 characters</p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                pwForm.patch('/portal/profile/password', { onSuccess: () => pwForm.reset() });
                            }}
                            className="space-y-4"
                        >
                            <InputField
                                label="Current Password"
                                type="password"
                                value={pwForm.data.current_password}
                                onChange={(v) => pwForm.setData('current_password', v)}
                                error={pwForm.errors.current_password}
                                placeholder="Your current password"
                            />

                            <div className="border-t border-slate-100 pt-4 space-y-4">
                                <InputField
                                    label="New Password"
                                    type="password"
                                    value={pwForm.data.password}
                                    onChange={(v) => pwForm.setData('password', v)}
                                    error={pwForm.errors.password}
                                    placeholder="New password"
                                />
                                <InputField
                                    label="Confirm New Password"
                                    type="password"
                                    value={pwForm.data.password_confirmation}
                                    onChange={(v) => pwForm.setData('password_confirmation', v)}
                                    placeholder="Repeat new password"
                                />
                            </div>

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={pwForm.processing}
                                    className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                                               text-sm font-semibold px-5 py-2.5 rounded-xl
                                               disabled:opacity-50 transition-colors duration-150"
                                >
                                    {pwForm.processing ? (
                                        <>
                                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Updating...
                                        </>
                                    ) : 'Update Password'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="mt-6 bg-white rounded-xl border border-red-200 shadow-sm p-5">
                    <h2 className="text-sm font-semibold text-red-700">Danger zone</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Deleting your account is permanent. You must transfer ownership of any business you solely own first.
                    </p>
                    <button
                        onClick={() => {
                            if (window.confirm('Delete your account permanently?')) {
                                router.post('/portal/account');
                            }
                        }}
                        className="mt-3 rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                    >
                        Delete account
                    </button>
                </div>
            </div>
        </PortalLayout>
    );
}
