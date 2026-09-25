import PortalLayout from '@/layouts/PortalLayout';
import { useForm, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

interface App {
    id: number;
    name: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface Props {
    applications: App[];
    flash: { plain_token: string } | null;
}

export default function ApiApplications({ applications, flash }: Props) {
    const form = useForm({ name: '', expires_at: '' });
    const [token] = useState<string | null>(flash?.plain_token ?? null);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/portal/settings/api', { onSuccess: () => form.reset() });
    };

    const revoke = (id: number) => {
        if (!window.confirm('Revoke this API application? The token will stop working immediately.')) return;
        router.delete(`/portal/settings/api/${id}`);
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">API applications</h1>
                <p className="text-sm text-slate-500 mt-0.5">Create Bearer tokens to connect external apps to your accounting data.</p>
            </div>

            {token && (
                <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm font-semibold text-amber-900">Copy your token now — it will not be shown again:</p>
                    <code className="mt-2 block rounded-lg bg-white p-3 text-sm break-all border border-amber-200">{token}</code>
                    <p className="text-xs text-amber-700 mt-2">Use as <code>Authorization: Bearer YOUR_TOKEN</code> for <code>/api/v1/*</code>.</p>
                </div>
            )}

            <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6 max-w-2xl">
                <h2 className="text-sm font-semibold text-slate-900 mb-3">Create application</h2>
                <form onSubmit={submit} className="flex flex-col sm:flex-row gap-3">
                    <input
                        className="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        placeholder="e.g. My Zapier integration"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                    <input
                        type="date"
                        className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        value={form.data.expires_at}
                        onChange={(e) => form.setData('expires_at', e.target.value)}
                    />
                    <button disabled={form.processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        Create
                    </button>
                </form>
                {form.errors.name && <p className="text-xs text-red-500 mt-2">{form.errors.name}</p>}
            </div>

            <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden max-w-3xl">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-slate-500">
                        <tr><th className="px-4 py-2 text-left">Name</th><th className="px-4 py-2 text-left">Last used</th><th className="px-4 py-2 text-left">Expires</th><th className="px-4 py-2"></th></tr>
                    </thead>
                    <tbody>
                        {applications.length === 0 ? (
                            <tr><td colSpan={4} className="px-4 py-8 text-center text-slate-400">No applications yet. Create one above.</td></tr>
                        ) : applications.map((app) => (
                            <tr key={app.id} className="border-t border-slate-100">
                                <td className="px-4 py-3 font-medium">{app.name}</td>
                                <td className="px-4 py-3 text-slate-500">{app.last_used_at ?? 'Never'}</td>
                                <td className="px-4 py-3 text-slate-500">{app.expires_at ?? 'Never'}</td>
                                <td className="px-4 py-3 text-right">
                                    <button onClick={() => revoke(app.id)} className="text-sm text-red-600 hover:underline">Revoke</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-6 text-xs text-slate-500 max-w-3xl">
                <p>Docs: <a href="/docs/api-documentation.html" className="text-indigo-600 hover:underline">API documentation</a> — base URL <code>/api/v1</code>, lists are paginated, tokens are company-scoped.</p>
            </div>
        </PortalLayout>
    );
}
