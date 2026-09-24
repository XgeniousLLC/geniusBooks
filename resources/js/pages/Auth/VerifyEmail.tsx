import GuestLayout from '@/layouts/GuestLayout';
import { router, usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';

export default function VerifyEmail({ status }: { status?: string | null }) {
    const { auth } = usePage<PageProps>().props;

    return (
        <GuestLayout
            title="Verify your email"
            subtitle="We sent a verification link to your inbox"
        >
            {status === 'verification-link-sent' && (
                <div className="mb-5 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 px-4 py-3 rounded-xl">
                    A new verification link has been sent.
                </div>
            )}

            <p className="text-sm text-gray-600 leading-relaxed">
                Before continuing, please confirm your email address by clicking the link we
                emailed you. If you did not receive it, we can send another.
            </p>

            <p className="mt-4 text-sm text-gray-500">
                Signed in as <span className="font-medium text-gray-700">{auth?.user?.email}</span>
            </p>

            <div className="mt-6 flex items-center justify-between gap-3">
                <button
                    onClick={() => router.post('/portal/email/verification-notification')}
                    className="inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 px-4 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                    Resend verification email
                </button>

                <button
                    onClick={() => router.post('/portal/logout')}
                    className="text-sm text-gray-500 hover:text-gray-700 underline"
                >
                    Log out
                </button>
            </div>
        </GuestLayout>
    );
}
