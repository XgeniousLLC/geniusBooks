import { useForm } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <GuestLayout title="Set new password" subtitle="Choose a strong password for your account">
            <form onSubmit={(e) => { e.preventDefault(); post('/portal/reset-password'); }} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Email</label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                        required
                    />
                    {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">New Password</label>
                    <input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                        required
                        autoFocus
                    />
                    {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                        required
                    />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full bg-indigo-600 text-white py-2 px-4 rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                >
                    {processing ? 'Resetting...' : 'Reset password'}
                </button>
            </form>
        </GuestLayout>
    );
}
