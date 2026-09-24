import { useForm, Link, usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';
import { type PageProps } from '@/types';

export default function ForgotPassword() {
    const { flash } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    return (
        <GuestLayout title="Forgot password?" subtitle="Enter your email and we'll send a reset link">
            {flash?.status && (
                <p className="mb-4 text-sm text-green-700">{flash.status}</p>
            )}

            <form onSubmit={(e) => { e.preventDefault(); post('/portal/forgot-password'); }} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Email</label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        required
                        autoFocus
                    />
                    {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full bg-indigo-600 text-white py-2 px-4 rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                >
                    {processing ? 'Sending...' : 'Send reset link'}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-600">
                <Link href="/portal/login" className="text-indigo-600 hover:underline">Back to login</Link>
            </p>
        </GuestLayout>
    );
}
