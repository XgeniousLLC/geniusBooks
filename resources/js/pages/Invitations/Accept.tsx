import GuestLayout from '@/layouts/GuestLayout';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Props {
    token: string;
    invitation: {
        email: string;
        role_label: string;
        company: string;
    };
    expired: boolean;
    authenticated: boolean;
}

export default function AcceptInvitation({ token, invitation, expired, authenticated }: Props) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/portal/invitations/${token}`);
    };

    if (expired) {
        return (
            <GuestLayout title="Invitation expired" subtitle={`The invitation to ${invitation.company}`}>
                <p className="text-sm text-gray-600">
                    This invitation is no longer valid. Please ask an administrator to invite you again.
                </p>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout
            title={`Join ${invitation.company}`}
            subtitle={`You have been invited as ${invitation.role_label}`}
        >
            <p className="text-sm text-gray-600 mb-5">
                Invited email: <span className="font-medium text-gray-800">{invitation.email}</span>
            </p>

            <form onSubmit={submit} className="space-y-5">
                {!authenticated && (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Your name</label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                            />
                            {form.errors.name && <p className="mt-1 text-xs text-red-500">{form.errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <input
                                type="password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                required
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                            />
                            {form.errors.password && <p className="mt-1 text-xs text-red-500">{form.errors.password}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Confirm password</label>
                            <input
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                required
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white"
                            />
                        </div>
                    </>
                )}

                {form.errors.email && <p className="text-xs text-red-500">{form.errors.email}</p>}

                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-3 rounded-xl disabled:opacity-50 transition-colors"
                >
                    {form.processing ? 'Joining...' : authenticated ? 'Accept invitation' : 'Create account & join'}
                </button>
            </form>
        </GuestLayout>
    );
}
