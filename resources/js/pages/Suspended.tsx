import GuestLayout from '@/layouts/GuestLayout';
import { Link, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';

export default function Suspended() {
    const { flash } = usePage<PageProps>().props;

    return (
        <GuestLayout title="Workspace suspended" subtitle="Access is temporarily unavailable">
            {flash?.error && (
                <div className="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 px-4 py-3 rounded-xl">
                    {flash.error}
                </div>
            )}

            <p className="text-sm text-gray-600 leading-relaxed">
                This business workspace has been suspended by the platform administrator. If you
                believe this is a mistake, please contact support.
            </p>

            <div className="mt-6">
                <Link
                    href="/portal/login"
                    className="inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 px-4 rounded-xl transition-colors"
                >
                    Back to sign in
                </Link>
            </div>
        </GuestLayout>
    );
}
