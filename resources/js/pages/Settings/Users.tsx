import PortalLayout from '@/layouts/PortalLayout';
import { router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_label: string | null;
    is_active: boolean;
    is_self: boolean;
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    role_label: string;
    expires_at: string;
}

interface RoleOption {
    value: string;
    label: string;
}

interface Props {
    members: Member[];
    invitations: Invitation[];
    roles: RoleOption[];
}

export default function UsersSettings({ members, invitations, roles }: Props) {
    const inviteForm = useForm({ email: '', role: roles[0]?.value ?? 'staff' });

    const submitInvite = (e: FormEvent) => {
        e.preventDefault();
        inviteForm.post('/portal/settings/invitations', {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset('email'),
        });
    };

    const changeRole = (member: Member, role: string) => {
        router.patch(`/portal/settings/members/${member.id}/role`, { role }, { preserveScroll: true });
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Users &amp; Roles</h1>
                <p className="text-sm text-slate-500 mt-0.5">Manage who can access this business and what they can do.</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h2 className="text-sm font-semibold text-slate-800">Members</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                                        <th className="px-5 py-3 font-medium">Member</th>
                                        <th className="px-5 py-3 font-medium">Role</th>
                                        <th className="px-5 py-3 font-medium text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {members.map((member) => (
                                        <tr key={member.id} className={member.is_active ? '' : 'opacity-60'}>
                                            <td className="px-5 py-3">
                                                <p className="font-medium text-slate-800">
                                                    {member.name} {member.is_self && <span className="text-xs text-slate-400">(you)</span>}
                                                </p>
                                                <p className="text-xs text-slate-500">{member.email}</p>
                                                {!member.is_active && <p className="text-xs text-red-500">Deactivated</p>}
                                            </td>
                                            <td className="px-5 py-3">
                                                <select
                                                    value={member.role ?? ''}
                                                    onChange={(e) => changeRole(member, e.target.value)}
                                                    aria-label={`Role for ${member.name}`}
                                                    className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                >
                                                    {roles.map((r) => (
                                                        <option key={r.value} value={r.value}>{r.label}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="px-5 py-3 text-right whitespace-nowrap">
                                                {member.is_active ? (
                                                    <button
                                                        onClick={() => router.patch(`/portal/settings/members/${member.id}/deactivate`, {}, { preserveScroll: true })}
                                                        disabled={member.is_self}
                                                        className="text-xs font-medium text-amber-600 hover:text-amber-800 disabled:opacity-40"
                                                    >
                                                        Deactivate
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => router.patch(`/portal/settings/members/${member.id}/reactivate`, {}, { preserveScroll: true })}
                                                        className="text-xs font-medium text-green-600 hover:text-green-800"
                                                    >
                                                        Reactivate
                                                    </button>
                                                )}
                                                <button
                                                    onClick={() => router.delete(`/portal/settings/members/${member.id}`, { preserveScroll: true })}
                                                    disabled={member.is_self}
                                                    className="ml-3 text-xs font-medium text-red-600 hover:text-red-800 disabled:opacity-40"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h2 className="text-sm font-semibold text-slate-800">Pending invitations</h2>
                        </div>
                        {invitations.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-slate-500">No pending invitations.</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {invitations.map((invitation) => (
                                    <li key={invitation.id} className="flex items-center justify-between px-5 py-3">
                                        <div>
                                            <p className="text-sm font-medium text-slate-800">{invitation.email}</p>
                                            <p className="text-xs text-slate-500">
                                                {invitation.role_label} · expires {new Date(invitation.expires_at).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <button
                                            onClick={() => router.delete(`/portal/settings/invitations/${invitation.id}`, { preserveScroll: true })}
                                            className="text-xs font-medium text-red-600 hover:text-red-800"
                                        >
                                            Revoke
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm h-fit">
                    <div className="px-5 py-4 border-b border-slate-100">
                        <h2 className="text-sm font-semibold text-slate-800">Invite a teammate</h2>
                    </div>
                    <form onSubmit={submitInvite} className="p-5 space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
                            <input
                                type="email"
                                value={inviteForm.data.email}
                                onChange={(e) => inviteForm.setData('email', e.target.value)}
                                required
                                className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            {inviteForm.errors.email && <p className="mt-1 text-xs text-red-500">{inviteForm.errors.email}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1.5">Role</label>
                            <select
                                value={inviteForm.data.role}
                                onChange={(e) => inviteForm.setData('role', e.target.value)}
                                className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                {roles.map((r) => (
                                    <option key={r.value} value={r.value}>{r.label}</option>
                                ))}
                            </select>
                        </div>
                        <button
                            type="submit"
                            disabled={inviteForm.processing}
                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 rounded-xl disabled:opacity-50 transition-colors"
                        >
                            {inviteForm.processing ? 'Sending...' : 'Send invitation'}
                        </button>
                    </form>
                </div>
            </div>
        </PortalLayout>
    );
}
