import { useForm, Link, usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';
import { type PageProps } from '@/types';
import { useState } from 'react';

export default function Login() {
    const { flash } = usePage<PageProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    return (
        <GuestLayout title="Welcome back" subtitle="Sign in to your support portal">
            {flash?.status && (
                <div className="mb-5 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 px-4 py-3 rounded-xl">
                    <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {flash.status}
                </div>
            )}

            {errors.email && (
                <div className="mb-5 flex items-start gap-2 text-sm text-red-700 bg-red-50 border border-red-200 px-4 py-3 rounded-xl">
                    <svg className="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {errors.email}
                </div>
            )}

            <form onSubmit={(e) => { e.preventDefault(); post('/portal/login'); }} className="space-y-5">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                        Email address
                    </label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@example.com"
                        className="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 placeholder-gray-400
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white
                                   transition-all duration-150"
                        required
                        autoFocus
                    />
                </div>

                <div>
                    <div className="flex items-center justify-between mb-1.5">
                        <label className="block text-sm font-medium text-gray-700">Password</label>
                        <Link href="/portal/forgot-password" className="text-xs text-indigo-600 hover:underline font-medium">
                            Forgot password?
                        </Link>
                    </div>
                    <div className="relative">
                        <input
                            type={showPassword ? 'text' : 'password'}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            className="w-full px-4 py-3 pr-11 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 placeholder-gray-400
                                       focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white
                                       transition-all duration-150"
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            tabIndex={-1}
                        >
                            {showPassword ? (
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            ) : (
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            )}
                        </button>
                    </div>
                </div>

                <div className="flex items-center">
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                        />
                        <span className="text-sm text-gray-600">Remember me for 30 days</span>
                    </label>
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700
                               text-white text-sm font-semibold py-3 px-4 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                               disabled:opacity-50 disabled:cursor-not-allowed
                               transition-colors duration-150 shadow-sm shadow-indigo-200"
                >
                    {processing ? (
                        <>
                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Signing in...
                        </>
                    ) : (
                        <>
                            Sign in
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </>
                    )}
                </button>
            </form>

            <div className="mt-6 pt-6 border-t border-gray-100 text-center space-y-2">
                <p className="text-sm text-gray-500">
                    Don't have an account?{' '}
                    <Link href="/portal/register" className="text-indigo-600 hover:underline font-medium">
                        Create one free
                    </Link>
                </p>
            </div>

            {/* Demo credentials hint */}
            <div className="mt-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                <p className="text-xs font-semibold text-amber-700 mb-1">Demo credentials</p>
                <p className="text-xs text-amber-600 font-mono">test@example.com / password</p>
            </div>
        </GuestLayout>
    );
}
